# Conversational Agent – Funcionalidades

Documento funcional del proyecto Laravel/Filament que opera como panel de atención omnicanal con automatizaciones de IA, RAG, métricas conversacionales y servicios de cobertura geográfica. El frontend/admin vive en `/admin`; la raíz (`/`) redirige allí.

## Arquitectura rápida
- Laravel + Filament para el backoffice (con WebSockets vía Reverb para actualizaciones en vivo).
- Integraciones con n8n (webhooks entrantes/salientes), Pinecone (búsquedas vectoriales), servicio externo de embeddings/extracción de texto y Google Gemini (voz y chat en tiempo real).
- Jobs en cola para procesamiento pesado (indexado RAG, importación KML).
- Servidores Node auxiliares: `proxy-server.js` (puente WebSocket a Gemini Live) y `twilio-server.js` (streaming voz Twilio ↔ Gemini con transcodificación 8k/24k y barge-in).

## API y webhooks (routes/api.php)
- `POST /api/rag/search` → Busca similitud en Pinecone a partir de un embedding del texto `query`; filtra por `store`, `doc_type` y solo documentos vigentes (`App\Http\Controllers\RagSearchController`).
- `GET /api/ping` → Health simple.
- `POST /api/n8n/telegram/message` → Ingreso de mensajes desde n8n/Telegram. Upsert de canal/contacto, crea conversación y mensaje, dispara evento `MessageCreated`, actualiza `last_message_at` (`N8n\InboundController`).
- `GET /api/agents/{slug}/prompt` → Devuelve (con ETag) la versión activa del prompt de un agente, cacheada 6h (`Api\AgentPromptController`).
- Sincronización con n8n para resúmenes:
  - `GET /api/conversations/{id}/messages` → Últimos N mensajes ordenados para que n8n resuma.
  - `POST /api/conversations/{id}/summary` → Guarda resumen + meta y emite broadcast `summary.updated` (`Api\ConversationSyncController`).
- Enrutamiento y mensajes salientes:
  - `GET /api/conversations/{id}/routing` → Modo actual (ai/human/hybrid) y usuario asignado.
  - `POST /api/conversations/{id}/outbound/admin` → Enviar texto escrito en el panel hacia n8n/Telegram y registrar mensaje outbound (`Api\ConversationRoutingController`).
- Analytics y métricas:
  - `POST /api/messages/{id}/analytics` → Guarda analítica por mensaje (sentiment, flags tox/abuse/pii, intent/entities).
  - `POST /api/conversations/{id}/metrics` → Guarda métricas agregadas (sentiment, tiempos, FCR, CSAT predicha, churn, intents) y emite `analytics.updated`.
  - `POST /api/conversations/{conversation}/recommendations` → Persiste markdown de recomendaciones y metadatos, broadcast `recommendations.updated`.
- Cobertura:
  - `POST /api/coverage/check` → Valida si unas coordenadas caen dentro de alguna zona (opcionalmente filtrando por departamento/provincia/distrito). Devuelve zona y metadatos si hay cobertura.
  - `GET /api/coverage/locations` → Búsqueda jerárquica de departamentos → provincias → distritos y previsualización de zonas por distrito.
- Voz Twilio:
  - `POST /api/twilio/voice` → Genera TwiML que conecta la llamada a un WebSocket (`services.twilio.websocket_url` o `wss://{host}/media-stream`) y cierra con un mensaje de voz en español (`App\Http\Controllers\TwilioController`).

## Panel de administración (Filament)
- **Conversaciones** (`App\Filament\Resources\ConversationResource` + vista `resources/views/filament/conversations/view.blade.php`):
  - Timeline con mensajes y adjuntos (imágenes, video, audio, archivos) usando Spatie Media Library.
  - Toggle IA/Humano (handover y resume) con auditoría de fechas.
  - Envío de mensajes outbound con texto, archivos o audio grabado en vivo; webhook a n8n con URLs firmadas de adjuntos.
  - Resumen y recomendaciones: botones disparan webhooks a n8n; la UI muestra loaders, fallback de refresco y escucha broadcasts `summary.updated` / `recommendations.updated`.
  - Analytics en vivo (sentiment, CSAT/churn, FCR, AHT, intents) y badges por mensaje (toxicity/PII/abuse).
  - Live updates por Reverb/Laravel Echo en el canal privado `conversations.{id}` para nuevos mensajes, resúmenes y analytics.
- **Agentes y prompts** (`App\Filament\Resources\AgentResource`):
  - CRUD de agentes (slug estable).
  - Relation manager de versiones de prompt con Markdown, parámetros, notas y versionado automático. Acción “Activar” publica y cachea la versión para el endpoint público; “Duplicar” clona a borrador.
- **Documentos RAG** (`App\Filament\Resources\RagDocumentResource`):
  - Subida de PDFs/DOCX/TXT, metadatos (`doc_type`, `store`, `version`, `extra`) y control de estado/activación.
  - Acciones: reindex suave/hard (limpia Pinecone), activar/desactivar (borra vectores y marca disabled), borrar (limpia archivo y vectores) y reindex masivo.
  - Polling cuando hay documentos en `pending/processing`.
- **Cobertura**:
  - CRUD de zonas (`CoverageZoneResource`) con selects dependientes departamento→provincia→distrito y mapa Leaflet en la vista para el polígono.
  - Página “Subir KML de cobertura” (`UploadCoverageKml`) que encola `ProcessCoverageKmlJob`, opcionalmente resetea datos y guarda notas.
- **Otros**:
  - Widget `TelcoKpis` con % autoservicio, cantidad de sentiment negativo y AHT aproximado.
  - Página de backups (`App\Filament\Pages\Backups`) y perfil (`MyProfileCustomPage`).

## Motor RAG y vectores
- **Ingesta** (`App\Jobs\ProcessRagDocument`):
  1) Lee archivo del disco configurado (`files.documents_disk`), extrae texto vía `App\Services\Extraction\Extractor` (HTTP a `/extract` del servicio `EMBEDDER_BASE_URL`), limpia UTF-8 y chunkifica (`App\Support\Chunker`).
  2) Genera embeddings con `App\Services\Embedding\Embedder` (HTTP a `/embed`).
  3) Upsert en Pinecone (`App\Services\Vector\PineconeClient`) con metadatos sanitizados (doc id, versión, tipo, título, store, texto, extra, `vigente=true`).
  4) Actualiza estado (`pending|processing|ready|failed`), `vector_count`, `indexed_at` y hash/versión indexada.
- **Gestión** (`App\Domain\Rag\RagDocumentIndexer`): decide reindex suave/hard, habilita/deshabilita y borra vectores por documento; usa namespace `store` o `PINECONE_NAMESPACE`.
- **Búsqueda** (`/api/rag/search`): arma filtro `vigente=true`, `store` y opcional `doc_type` y `topK`, consulta con `PineconeQueryClient` y devuelve matches con metadata.
- **Bindings de servicios** en `AppServiceProvider`: Pinecone client/query HTTP, extractor HTTP y embedder HTTP.

## Cobertura geográfica
- Servicio `CoverageService` ejecuta point-in-polygon sobre cada zona candidata (filtrada por jerarquía si se envían nombres).
- Importación KML (`ImportCoverageKmlService` + job `ProcessCoverageKmlJob`):
  - Lee `Placemark`/`MultiGeometry`, extrae `ExtendedData` (DEPARTAMEN/PROVINCIA/DISTRITO/Puntaje), crea jerarquía `CoverageDepartment/Province/District` y zonas con polígonos y score.
  - `replace_existing=true` trunca tablas antes de cargar.

## Voz y tiempo real con Gemini
- **Vista /voice** (`resources/views/voice-call.blade.php`): UI de micrófono con visualizador; usa `resources/js/gemini-live-client.js` para grabar, enviar y reproducir audio vía WebSocket al proxy (por defecto `ws://localhost:8081`).
- **Proxy Gemini Live** (`proxy-server.js`): WebSocket local que reenvía mensajes a `generativelanguage.googleapis.com` usando `GOOGLE_API_KEY`, bufferiza hasta que abra la conexión upstream.
- **Twilio streaming** (`twilio-server.js`):
  - Endpoint HTTP `/twilio/voice` (Twilio `<Stream>`) y WebSocket `/media-stream`.
  - Convierte audio mulaw 8k ↔ PCM 24k con LUTs, aplica ganancia, VAD/barge-in para cortar audio de Gemini cuando el usuario habla, reenvía media como `realtimeInput` a Gemini y convierte respuestas a mulaw para Twilio.
  - Timeouts de silencio de 15s cierran la llamada.

## Eventos y observadores
- Broadcast (canal privado `conversations.{id}`):
  - `message.created`, `summary.updated`, `analytics.updated`, `recommendations.updated` (`App\Events\*`).
- `MessageObserver`: en `created` envía webhook `N8N_ANALYZE_MESSAGE_WEBHOOK` con datos básicos para análisis externo.

## Variables y configuración clave
- Pinecone: `PINECONE_API_KEY`, `PINECONE_BASE_URL`, `PINECONE_INDEX`, `PINECONE_NAMESPACE`.
- Embeddings/Extracción: `EMBEDDER_BASE_URL` (endpoints `/embed` y `/extract` usados por servicios HTTP).
- n8n: `N8N_SUMMARIZE_WEBHOOK`, `N8N_ADMIN_OUTBOUND_WEBHOOK`, `N8N_ANALYZE_MESSAGE_WEBHOOK`, `N8N_RECOMMENDATIONS_WEBHOOK`.
- Gemini/voz: `GOOGLE_API_KEY`; opcional `services.twilio.websocket_url` para TwilioController; proxy Gemini usa `proxy-server.js` y puerto 8081 por defecto.
- Reverb/WebSockets: `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` (usados en la vista de conversación para Echo).
- Archivos: `files.documents_disk` (disco de documentos RAG) y `APP_URL`/storage público para adjuntos del chat.


# 🤖 Conversational AI Agent & Omnichannel Platform

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel)
![Filament](https://img.shields.io/badge/Filament-3-F28D1A?style=for-the-badge&logo=filament)
![Node.js](https://img.shields.io/badge/Node.js-20-339933?style=for-the-badge&logo=nodedotjs)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker)
![Twilio](https://img.shields.io/badge/Twilio-Voice-F22F46?style=for-the-badge&logo=twilio)
![Gemini](https://img.shields.io/badge/Google-Gemini_Multimodal-8E75B2?style=for-the-badge&logo=googlebard)
![Pinecone](https://img.shields.io/badge/Pinecone-Vector_DB-000000?style=for-the-badge&logo=pinecone)

Plataforma integral de atención al cliente que combina un **Agente de Voz IA en tiempo real** (Google Gemini + Twilio), un **Panel de Administración Omnicanal** (Laravel Filament) y un sistema **RAG (Retrieval-Augmented Generation)** para respuestas precisas basadas en documentos corporativos.

---

## 🚀 Características Principales

### 🗣️ Agente de Voz IA (SantIAgo)
- **Conversación Natural:** Utiliza `Gemini 2.5 Flash` con capacidad multimodal nativa de audio.
- **Baja Latencia:** Streaming de audio bidireccional mediante WebSockets y transcodificación optimizada (G.711 µ-law ↔ PCM 24kHz).
- **Interrupciones (Barge-in):** El agente se detiene automáticamente cuando el usuario habla.
- **Herramientas (Function Calling):**
    - `validar_identidad`: Consulta de DNI/CE.
    - `validar_cobertura`: Verificación de factibilidad técnica por dirección.
    - `consultar_rag`: Respuestas sobre planes y servicios basadas en base de conocimiento.
- **Grabación:** Almacenamiento automático de llamadas y disponibilidad inmediata en el panel.

### 🧠 Motor RAG (Base de Conocimiento)
- **Ingesta de Documentos:** Soporte para PDF, DOCX, TXT.
- **Vectorización:** Embeddings de alta calidad (1536 dimensiones) almacenados en **Pinecone**.
- **Búsqueda Semántica:** Recuperación de contexto relevante para responder preguntas del usuario sin alucinaciones.

### 📊 Panel de Administración (Filament)
- **Gestión de Llamadas:** Historial completo, reproducción de grabaciones, estado de la llamada.
- **Conversaciones Chat:** Atención híbrida (IA + Humano) con timeline en tiempo real (Reverb/WebSockets).
- **Métricas:** Dashboards de KPIs, análisis de sentimiento, tiempos de atención.
- **Cobertura:** Gestión visual de zonas de cobertura (mapas) e importación KML.

---

## 🏗️ Arquitectura del Sistema

El sistema se compone de varios servicios contenerizados orquestados con Docker Compose:

| Servicio | Contenedor | Descripción | Puerto |
| :--- | :--- | :--- | :--- |
| **Backend** | `qonpania-php` | Laravel 11 + Filament. API y Panel Admin. | 9000 (FPM) |
| **Cola** | `qonpania-queue` | Worker para procesos en segundo plano (RAG, descargas). | - |
| **Base de Datos** | `qonpania-db` | MySQL 8.0 para datos relacionales. | 3306 |
| **Cache/KV** | `qonpania-redis` | Redis para cache, colas y eventos. | 6379 |
| **WebSockets** | `reverb` | Laravel Reverb para eventos en tiempo real al frontend. | 8080 |
| **Twilio Server** | `twilio-server` | **Node.js**. Servidor WebSocket para llamadas de voz. | 8082 |
| **Gemini Proxy** | `gemini-proxy` | **Node.js**. Proxy seguro para Gemini Realtime API. | 8081 |

---

## 🛠️ Requisitos Previos

1.  **Docker & Docker Compose** (v2 recommended).
2.  **Cuenta de Twilio:**
    - Número de teléfono comprado.
    - Credenciales: `Account SID`, `Auth Token`.
3.  **Google Cloud Project:**
    - API Key con acceso a `Gemini API` (`generativelanguage.googleapis.com`).
4.  **Pinecone:**
    - Índice creado con **Dimensiones: 1536** y métrica **Cosine**.
    - API Key y Host URL.
5.  **Dominio con SSL** (para producción).

---

## ⚙️ Instalación y Configuración

### 1. Clonar el Repositorio
```bash
git clone <repo-url>
cd conversational-agent
```

### 2. Configurar Variables de Entorno
Copia el archivo de ejemplo y edítalo:
```bash
cp .env.example .env
```

**Variables Críticas:**

```ini
APP_URL=https://tu-dominio.com

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=qonpania-db
DB_PORT=3306
DB_DATABASE=qonpania
DB_USERNAME=root
DB_PASSWORD=secret

# Redis
REDIS_HOST=qonpania-redis
REDIS_PASSWORD=secret

# Google Gemini
GOOGLE_API_KEY=AIzaSy...

# Twilio
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_NUMBER=+1234567890

# Pinecone (RAG)
PINECONE_API_KEY=pcsk_...
PINECONE_BASE_URL=https://index-name.svc.pinecone.io
PINECONE_INDEX=rag-main
PINECONE_NAMESPACE=default

# Embeddings Service (Local Python Service)
EMBEDDER_BASE_URL=http://host.docker.internal:8001
```

### 3. Levantar Servicios con Docker
```bash
docker compose up -d --build
```

### 4. Inicializar Aplicación
```bash
# Instalar dependencias PHP
docker compose exec qonpania-php composer install

# Generar Key
docker compose exec qonpania-php php artisan key:generate

# Migraciones y Seeders
docker compose exec qonpania-php php artisan migrate --seed

# Enlace simbólico para storage
docker compose exec qonpania-php php artisan storage:link
```

---

## 🚀 Despliegue en Producción (Nginx)

Para que Twilio pueda conectar con el servidor de voz, necesitas exponer el puerto `8082` y `80` (Laravel) bajo un dominio con SSL.

### Configuración de Nginx

Crea un archivo `/etc/nginx/sites-available/tu-dominio.conf`:

```nginx
upstream twilio_ws {
    server 127.0.0.1:8082; # Apunta al puerto expuesto del contenedor twilio-server
    keepalive 64;
}

server {
    server_name tu-dominio.com;
    # ... configuración SSL (Certbot) ...

    # Laravel App
    location / {
        proxy_pass http://127.0.0.1:8000; # O fastcgi_pass a PHP-FPM
        # ... headers proxy standard ...
    }

    # Twilio Voice & Media Stream (CRÍTICO)
    location /twilio/ {
        proxy_pass http://twilio_ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }

    location /media-stream {
        proxy_pass http://twilio_ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
}
```

### Configuración del Webhook en Twilio
En la consola de Twilio, ve a tu número de teléfono activo y configura:

- **Voice & Fax** -> **A Call Comes In**:
    - **Webhook**: `https://tu-dominio.com/twilio/voice`
    - **Method**: `POST`

---

## 📖 Uso del Sistema

### Panel Administrativo
Accede a `https://tu-dominio.com/admin`.
- **Usuario:** `admin@qonpania.com` (o el creado en seeder)
- **Password:** `password`

### Gestión de RAG
1. Ve a la sección **RAG Documents**.
2. Sube un archivo PDF con información de tus planes/servicios.
3. El sistema procesará el archivo (estado `Processing` -> `Ready`).
4. ¡Listo! El agente de voz ahora usará esta información.

### Pruebas de Voz
Llama a tu número de Twilio. El agente debería:
1. Saludar como "SantIAgo".
2. Responder preguntas generales usando el RAG.
3. Validar identidad y cobertura si se le solicita.

---

---

## 📋 Historias de Usuario (User Stories) & Criterios de Aceptación

A continuación se detallan las funcionalidades del sistema organizadas por los **4 Perfiles de Usuario (Personas)** clave, con criterios de aceptación exhaustivos en formato Gherkin (Dado/Cuando/Entonces).

### 👤 Persona 1: Cliente Potencial
*Usuario final que llama o escribe buscando contratar servicios de internet.*

#### **US-01: Atención Conversacional Natural**
> **Como** Cliente Potencial,
> **Quiero** conversar con un agente que entienda mi forma natural de hablar,
> **Para** no sentirme frustrado por menús rígidos.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente llama y saluda informalmente ("Hola, qué tal"),
    - **Cuando** el agente procesa la entrada de audio,
    - **Entonces** debe responder con naturalidad y empatía en menos de 2 segundos.
- **Escenario 2 (Sad Path - Silencio):**
    - **Dado** que el cliente se queda callado por más de 10 segundos,
    - **Cuando** el agente detecta el silencio prolongado,
    - **Entonces** debe preguntar "¿Sigues ahí?" antes de cortar la llamada.
- **Escenario 3 (Edge Case - Ruido):**
    - **Dado** que hay mucho ruido de fondo en la llamada,
    - **Cuando** el agente intenta transcribir el audio,
    - **Entonces** debe pedir amablemente que repita si no entiende, evitando alucinar una respuesta.

#### **US-02: Interrupción (Barge-in)**
> **Como** Cliente Potencial,
> **Quiero** poder interrumpir al agente si ya entendí o quiero cambiar de tema,
> **Para** agilizar la llamada.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el agente está hablando,
    - **Cuando** el cliente dice "espera" o interrumpe,
    - **Entonces** el agente debe silenciarse inmediatamente (< 500ms) y escuchar.
- **Escenario 2 (Sad Path - Latencia):**
    - **Dado** que la red es lenta,
    - **Cuando** el cliente interrumpe,
    - **Entonces** el sistema debe priorizar el audio entrante para detener la reproducción lo antes posible.
- **Escenario 3 (Edge Case - Breve sonido):**
    - **Dado** que el cliente tose o hace un ruido corto,
    - **Cuando** el VAD (Voice Activity Detection) lo procesa,
    - **Entonces** el agente debe ignorarlo y continuar hablando sin interrumpirse.

#### **US-03: Validación de Cobertura**
> **Como** Cliente Potencial,
> **Quiero** saber si pueden instalar internet en mi casa,
> **Para** decidir si contrato el servicio.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente dicta su dirección exacta,
    - **Cuando** el sistema valida las coordenadas con la API,
    - **Entonces** el agente debe confirmar "TIENE COBERTURA" y ofrecer planes.
- **Escenario 2 (Sad Path - Sin Cobertura):**
    - **Dado** una dirección válida pero fuera de zona,
    - **Cuando** el sistema verifica la factibilidad,
    - **Entonces** el agente debe informar "No llegamos aún" y ofrecer avisar en el futuro.
- **Escenario 3 (Edge Case - Dirección Ambigua):**
    - **Dado** que el cliente dice una dirección incompleta (ej. "Calle Lima"),
    - **Cuando** el sistema encuentra múltiples coincidencias,
    - **Entonces** el agente debe preguntar "¿En qué distrito?" para desambiguar.

#### **US-04: Consultas sobre Planes (RAG)**
> **Como** Cliente Potencial,
> **Quiero** preguntar por precios y características específicas,
> **Para** elegir el plan que mejor se ajuste a mi presupuesto.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente pregunta por el "Plan Gamer",
    - **Cuando** el agente consulta la base de conocimiento (RAG),
    - **Entonces** debe responder con la velocidad y precio exacto del PDF vigente.
- **Escenario 2 (Sad Path - Info no disponible):**
    - **Dado** que el cliente pregunta por "TV Satelital",
    - **Cuando** el agente no encuentra información en los documentos,
    - **Entonces** debe responder "No ofrecemos ese servicio" sin inventar datos.
- **Escenario 3 (Edge Case - Alucinación):**
    - **Dado** que el cliente pregunta por precios de la competencia,
    - **Cuando** el agente genera la respuesta,
    - **Entonces** debe limitarse a hablar de su propia oferta sin inventar datos de terceros.

---

### 🤖 Persona 2: Entrenador de IA (Admin de Contenido)
*Encargado de mantener la "inteligencia" del agente, sus respuestas y documentos.*

#### **US-05: Ingesta de Conocimiento (RAG)**
> **Como** Entrenador de IA,
> **Quiero** subir nuevos tarifarios en PDF,
> **Para** que el agente actualice su oferta comercial al instante.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** un archivo PDF válido con nuevos planes,
    - **Cuando** se sube al módulo RAG,
    - **Entonces** el sistema debe procesarlo, generar vectores (1536 dim) y marcarlo como `Ready`.
- **Escenario 2 (Sad Path - Archivo Corrupto):**
    - **Dado** un archivo PDF dañado,
    - **Cuando** el sistema intenta procesarlo,
    - **Entonces** debe marcar el estado como `Failed` y notificar el error.
- **Escenario 3 (Edge Case - Archivo Grande):):**
    - **Dado** un PDF de 50MB,
    - **Cuando** el usuario intenta subirlo,
    - **Entonces** el sistema debe validar el límite de tamaño antes de procesar y rechazarlo si excede.

#### **US-06: Gestión de Versiones de Prompt**
> **Como** Entrenador de IA,
> **Quiero** ajustar y versionar el "System Prompt" del agente,
> **Para** mejorar su personalidad o corregir comportamientos sin tocar código.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que edito el prompt en el panel,
    - **Cuando** guardo una nueva versión,
    - **Entonces** el agente debe empezar a usar el nuevo prompt inmediatamente en las siguientes llamadas.
- **Escenario 2 (Sad Path - Prompt Vacío):**
    - **Dado** que intento guardar un prompt vacío,
    - **Cuando** presiono guardar,
    - **Entonces** el sistema debe impedir la acción y mostrar un error de validación.
- **Escenario 3 (Rollback):**
    - **Dado** que el nuevo prompt funciona mal,
    - **Cuando** el admin selecciona la versión anterior,
    - **Entonces** el sistema debe reactivar esa versión con un solo clic.

#### **US-07: Auditoría de Conversaciones**
> **Como** Entrenador de IA,
> **Quiero** leer las transcripciones y escuchar los audios,
> **Para** detectar fallos en el entendimiento del agente.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** una lista de llamadas recientes,
    - **Cuando** selecciono una interacción,
    - **Entonces** debo ver la transcripción completa y un reproductor de audio funcional.
- **Escenario 2 (Sad Path - Audio no disponible):**
    - **Dado** que la grabación falló en Twilio,
    - **Cuando** intento reproducir el audio,
    - **Entonces** el sistema debe mostrar un aviso "Grabación no disponible" en lugar de un error 404.
- **Escenario 3 (Filtros):**
    - **Dado** que busco una llamada específica,
    - **Cuando** uso el filtro por fecha o ID,
    - **Entonces** la tabla debe mostrar solo los resultados coincidentes.

---

### 💼 Persona 3: Ejecutivo de Ventas
*Agente humano que interviene cuando la IA no puede cerrar la venta o el cliente pide humano.*

#### **US-08: Handover (Toma de Control)**
> **Como** Ejecutivo de Ventas,
> **Quiero** tomar el control de una conversación de chat iniciada por la IA,
> **Para** cerrar una venta compleja o resolver una queja.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** una conversación activa con la IA,
    - **Cuando** hago clic en "Tomar (Humano)",
    - **Entonces** la IA debe dejar de responder y permitirme enviar mensajes manuales.
- **Escenario 2 (Sad Path - Concurrencia):**
    - **Dado** que dos ejecutivos intentan tomar la misma charla,
    - **Cuando** ambos hacen clic al mismo tiempo,
    - **Entonces** el sistema debe asignar al primero y avisar al segundo que ya fue tomada.
- **Escenario 3 (Reversión):**
    - **Dado** que terminé de atender al cliente,
    - **Cuando** hago clic en "Volver a IA",
    - **Entonces** la IA debe retomar la conversación fluidamente.

#### **US-09: Visualización de Contexto**
> **Como** Ejecutivo de Ventas,
> **Quiero** ver todo lo que el cliente habló con la IA antes de que yo entre,
> **Para** no pedirle que repita sus datos.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que asumo una conversación,
    - **Cuando** abro la ventana de chat,
    - **Entonces** se debe cargar el historial completo (mensajes de IA y Cliente).
- **Escenario 2 (Sad Path - Historial Largo):**
    - **Dado** una conversación de 100 mensajes,
    - **Cuando** la visualizo,
    - **Entonces** el sistema debe paginar correctamente sin colgar el navegador.
- **Escenario 3 (Datos Estructurados):**
    - **Dado** que la IA ya capturó datos,
    - **Cuando** reviso el panel lateral,
    - **Entonces** debo ver el DNI y Dirección validados.

#### **US-17: Chat en Tiempo Real**
> **Como** Ejecutivo de Ventas,
> **Quiero** ver los mensajes del cliente aparecer instantáneamente sin recargar la página,
> **Para** mantener una conversación fluida.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente envía un mensaje,
    - **Cuando** tengo abierta la vista de conversación,
    - **Entonces** el mensaje debe aparecer en pantalla en menos de 1 segundo (WebSocket).
- **Escenario 2 (Sad Path - Desconexión):**
    - **Dado** que se pierde la conexión a internet,
    - **Cuando** la conexión regresa,
    - **Entonces** la UI debe mostrar "Reconectando..." y sincronizar los mensajes faltantes.
- **Escenario 3 (Notificación):**
    - **Dado** que estoy en otra pestaña,
    - **Cuando** llega un nuevo mensaje,
    - **Entonces** el navegador debe mostrar una notificación push o reproducir un sonido.

#### **US-18: Envío de Multimedia (Imágenes/Docs)**
> **Como** Ejecutivo de Ventas,
> **Quiero** enviar cotizaciones en PDF o fotos de equipos al cliente,
> **Para** complementar la información textual.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** un archivo PDF en mi escritorio,
    - **Cuando** lo arrastro al área de chat y envío,
    - **Entonces** el cliente debe recibir el archivo adjunto en su Telegram/WhatsApp.
- **Escenario 2 (Sad Path - Formato Inválido):**
    - **Dado** un archivo `.exe` peligroso,
    - **Cuando** intento subirlo,
    - **Entonces** el sistema debe rechazar el archivo por motivos de seguridad.
- **Escenario 3 (Límite):**
    - **Dado** un archivo mayor a 10MB,
    - **Cuando** intento enviarlo,
    - **Entonces** el sistema debe avisar que excede el tamaño máximo permitido.

#### **US-19: Notas de Voz (Audio)**
> **Como** Ejecutivo de Ventas,
> **Quiero** grabar y enviar una nota de voz desde el navegador,
> **Para** explicar temas complejos más rápido que escribiendo.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que tengo micrófono habilitado,
    - **Cuando** presiono grabar, hablo y envío,
    - **Entonces** el cliente debe recibir un audio reproducible.
- **Escenario 2 (Sad Path - Sin Micrófono):**
    - **Dado** que no he dado permisos al navegador,
    - **Cuando** intento grabar,
    - **Entonces** el navegador debe mostrar una alerta solicitando habilitar el micrófono.
- **Escenario 3 (Cancelación):**
    - **Dado** una grabación en curso,
    - **Cuando** decido cancelar,
    - **Entonces** el audio se debe descartar sin enviarse.

#### **US-21: Resumen de Conversación On-Demand**
> **Como** Ejecutivo que recién entra al turno,
> **Quiero** generar un resumen de lo hablado en las últimas horas,
> **Para** ponerme al día en segundos.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** una conversación larga,
    - **Cuando** hago clic en "Generar Resumen",
    - **Entonces** en menos de 5 segundos debe aparecer un resumen generado por el LLM.
- **Escenario 2 (Sad Path - Error API):**
    - **Dado** que el servicio de LLM no responde,
    - **Cuando** solicito el resumen,
    - **Entonces** el sistema debe notificar "Intente más tarde" sin romper la interfaz.
- **Escenario 3 (Conversación Corta):**
    - **Dado** una conversación con solo un "Hola",
    - **Cuando** pido resumen,
    - **Entonces** el sistema debe indicar "Conversación recién iniciada, sin datos relevantes".

#### **US-22: Recomendaciones de Respuesta (Next Best Action)**
> **Como** Ejecutivo de Ventas,
> **Quiero** que la IA me sugiera qué responder o qué producto ofrecer,
> **Para** aumentar mis probabilidades de venta.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente pregunta por precios,
    - **Cuando** presiono "Generar Recomendaciones",
    - **Entonces** la IA debe sugerir un texto con el precio del plan adecuado.
- **Escenario 2 (Sad Path - Confusión):**
    - **Dado** que el cliente habla incoherencias,
    - **Cuando** pido sugerencia,
    - **Entonces** la IA debe sugerir "Pedir aclaración" o no sugerir nada.
- **Escenario 3 (Copiar):**
    - **Dado** una sugerencia visible,
    - **Cuando** hago clic en ella,
    - **Entonces** el texto se debe copiar al campo de entrada listo para editar.

#### **US-23: Métricas de Cliente en Tiempo Real**
> **Como** Ejecutivo de Ventas,
> **Quiero** ver el nivel de satisfacción (CSAT), riesgo de fuga (Churn) y los temas principales (Intents),
> **Para** adaptar mi tono y estrategia.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el cliente expresa enojo,
    - **Cuando** se actualizan las métricas,
    - **Entonces** el indicador CSAT debe bajar a rojo y el Churn subir.
- **Escenario 2 (Sad Path - Datos Insuficientes):**
    - **Dado** una conversación nueva,
    - **Cuando** reviso el panel,
    - **Entonces** los indicadores deben mostrar "Pendiente" o un guión.
- **Escenario 3 (Top Intents):**
    - **Dado** que la conversación avanza,
    - **Cuando** cambian los temas,
    - **Entonces** la lista de "Top Intents" se debe actualizar dinámicamente (ej. de "Ventas" a "Soporte").

---

### 🛠️ Persona 4: Administrador del Sistema (IT/Ops)
*Responsable de la salud técnica, seguridad y mantenimiento de la plataforma.*

#### **US-24: Análisis de Mensajes (Sentimiento, Toxicidad y PII)**
> **Como** Admin del Sistema,
> **Quiero** detectar automáticamente el sentimiento de cada mensaje y si contiene toxicidad o datos sensibles,
> **Para** garantizar la calidad y seguridad.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** un mensaje con un insulto,
    - **Cuando** el sistema lo procesa,
    - **Entonces** debe marcar un badge `Toxic` en rojo.
- **Escenario 2 (Happy Path):**
    - **Dado** un mensaje con DNI o teléfono,
    - **Cuando** el sistema lo analiza,
    - **Entonces** debe marcar un badge `PII` en amarillo.
- **Escenario 3 (Edge Case):**
    - **Dado** un falso positivo,
    - **Cuando** el admin lo revisa,
    - **Entonces** (idealmente) debería poder desmarcar o ignorar la alerta.

#### **US-10: Monitoreo de Salud (Health Check)**
> **Como** Admin del Sistema,
> **Quiero** ver un dashboard con el estado de la base de datos, Redis y espacio en disco,
> **Para** prevenir caídas del servicio.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que todos los servicios funcionan,
    - **Cuando** accedo al dashboard,
    - **Entonces** debo ver todos los indicadores en verde.
- **Escenario 2 (Sad Path - Redis Down):**
    - **Dado** que el servicio de Redis está caído,
    - **Cuando** reviso el estado,
    - **Entonces** debo ver un indicador rojo y una alerta.
- **Escenario 3 (Latencia):**
    - **Dado** que la base de datos responde lento,
    - **Cuando** el sistema mide la latencia,
    - **Entonces** debe mostrar un indicador amarillo advirtiendo tiempos altos.

#### **US-11: Gestión de Roles y Permisos**
> **Como** Admin del Sistema,
> **Quiero** asignar permisos específicos a los Ejecutivos de Ventas,
> **Para** que no puedan borrar configuraciones críticas del sistema.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** un nuevo usuario,
    - **Cuando** le asigno el rol "Vendedor",
    - **Entonces** solo debe poder ver el módulo de Conversaciones.
- **Escenario 2 (Sad Path - Acceso Denegado):**
    - **Dado** un usuario con rol "Vendedor",
    - **Cuando** intenta entrar a `/admin/settings`,
    - **Entonces** el sistema debe mostrar una página de error 403 Forbidden.
- **Escenario 3 (Super Admin):**
    - **Dado** un usuario con rol "Admin",
    - **Cuando** navega por el sistema,
    - **Entonces** debe tener acceso total e irrestricto.

#### **US-12: Visualización de Logs**
> **Como** Admin del Sistema,
> **Quiero** ver los logs de errores de Laravel directamente en el panel,
> **Para** diagnosticar problemas sin entrar al servidor por SSH.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que ocurrieron errores recientes,
    - **Cuando** entro al visor de Logs,
    - **Entonces** debo ver la lista de errores y poder hacer clic para ver el stack trace.
- **Escenario 2 (Filtro):**
    - **Dado** muchos logs de info y error,
    - **Cuando** filtro por "Critical",
    - **Entonces** solo se deben mostrar los errores críticos.
- **Escenario 3 (Limpieza):**
    - **Dado** que los logs ocupan mucho espacio,
    - **Cuando** presiono "Limpiar logs",
    - **Entonces** se deben eliminar los registros antiguos.

#### **US-13: Monitoreo de Jobs y Colas**
> **Como** Admin del Sistema,
> **Quiero** ver si los procesos de ingesta de documentos o descarga de grabaciones están atascados,
> **Para** reiniciar los workers si es necesario.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** jobs en ejecución,
    - **Cuando** reviso el monitor,
    - **Entonces** debo ver cuántos están pendientes, procesándose o completados.
- **Escenario 2 (Sad Path - Job Fallido):**
    - **Dado** un job que falla 3 veces,
    - **Cuando** pasa a la tabla `failed_jobs`,
    - **Entonces** el admin debe poder reintentarlo desde la UI.
- **Escenario 3 (Cola llena):**
    - **Dado** que la cola crece demasiado rápido,
    - **Cuando** el admin lo detecta,
    - **Entonces** debe poder decidir escalar los workers (acción manual fuera del sistema, pero informada).

#### **US-14: Gestión de Backups**
> **Como** Admin del Sistema,
> **Quiero** configurar y descargar copias de seguridad de la base de datos,
> **Para** recuperar el sistema ante un desastre.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que el backup nocturno se ejecutó,
    - **Cuando** accedo al módulo,
    - **Entonces** debo ver el archivo disponible para descarga.
- **Escenario 2 (Sad Path - Fallo):**
    - **Dado** que el disco está lleno,
    - **Cuando** el backup intenta ejecutarse,
    - **Entonces** debe fallar y notificar al admin.
- **Escenario 3 (Descarga):**
    - **Dado** un backup existente,
    - **Cuando** hago clic en descargar,
    - **Entonces** se debe bajar un archivo `.zip` (encriptado si se configuró).

#### **US-15: Importación Masiva de Cobertura (KML)**
> **Como** Admin del Sistema,
> **Quiero** actualizar las zonas de cobertura mediante archivos KML,
> **Para** mantener la base de datos sincronizada con Ingeniería.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** un archivo KML válido,
    - **Cuando** lo importo,
    - **Entonces** se debe lanzar un Job en background que actualice las zonas.
- **Escenario 2 (Sad Path - KML Inválido):**
    - **Dado** un archivo mal formado,
    - **Cuando** intento subirlo,
    - **Entonces** el sistema debe rechazarlo y mostrar un error de sintaxis.
- **Escenario 3 (Performance):**
    - **Dado** un KML con 10,000 polígonos,
    - **Cuando** se procesa,
    - **Entonces** debe hacerlo por lotes para no bloquear el servidor ni dar timeout.

#### **US-16: Gestión de Perfil y Seguridad**
> **Como** Admin del Sistema,
> **Quiero** poder cambiar mi contraseña y activar autenticación de dos factores (si disponible),
> **Para** proteger mi cuenta de accesos no autorizados.

**Criterios de Aceptación:**
- **Escenario 1 (Happy Path):**
    - **Dado** que cambio mi contraseña,
    - **Cuando** guardo los cambios,
    - **Entonces** el sistema debe requerir que me loguee de nuevo.
- **Escenario 2 (Sad Path - Contraseña Débil):**
    - **Dado** que intento usar "123456",
    - **Cuando** valido el formulario,
    - **Entonces** el sistema debe exigir una complejidad mínima.
- **Escenario 3 (2FA):**
    - **Dado** que activo 2FA,
    - **Cuando** intento loguearme la próxima vez,
    - **Entonces** el sistema debe pedir el código OTP.

---

## 🐛 Solución de Problemas Comunes

- **Error 404 en /twilio/voice:** Revisa la configuración de Nginx. La ruta debe apuntar al puerto 8082, no a Laravel.
- **Error 12200 (XML):** Asegúrate de que el TwiML generado sea válido. Revisa los logs de `twilio-server`.
- **Credenciales faltantes:** Si cambias el `.env`, reinicia los contenedores (`docker compose restart`). Especialmente `qonpania-queue`.
- **Pinecone Dimension Mismatch:** Asegúrate de que tu índice tenga **1536 dimensiones**.

---

Hecho con ❤️ por el equipo de **Qonpania**.

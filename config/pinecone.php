<?php
return [
  'key'      => env('PINECONE_API_KEY',''),
  'base_url' => env('PINECONE_BASE_URL',''),
  'index'    => env('PINECONE_INDEX','rag-main'),
  'namespace' => env('PINECONE_NAMESPACE', 'default'),
];

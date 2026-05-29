<?php
require_once __DIR__ . '/../_bootstrap.php';

api_require_method('POST');
api_destroy_session();
api_send(true, null, null);

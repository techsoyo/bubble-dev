<?php

require_once __DIR__ . '/../../api/bootstrap.php';
preflightHandle();
sendCorsHeaders();

// Include the actual jobs endpoint
require_once __DIR__ . '/../../api/endpoints/jobs.php';

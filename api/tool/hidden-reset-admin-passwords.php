<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['success' => false, 'message' => 'This diagnostic endpoint has been permanently disabled.']);

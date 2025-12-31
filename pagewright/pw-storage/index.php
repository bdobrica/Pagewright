<?php
/**
 * Pagewright Storage Directory - Access Denied
 * 
 * This file exists as a defense-in-depth measure.
 * If .htaccess fails or is not supported, this prevents directory listing
 * and provides a clear access denied message.
 */

http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');

exit('Access Denied: This directory contains sensitive data and cannot be accessed via web.');

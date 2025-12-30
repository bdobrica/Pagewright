<?php
declare(strict_types=1);

// --- Basic app config ---
const APP_NAME = 'Pagewright';
const BASE_PATH = __DIR__; // /pagewright/admin
const STORAGE_PATH = __DIR__ . '/../storage'; // /pagewright/storage

// IMPORTANT: must match your deployed URL path.
// Example: https://example.com/pagewright/admin
// If you install at domain root: https://example.com/admin
const ADMIN_BASE_URL = ''; // leave blank to auto-detect; see util/Http.php

// --- OAuth Provider Config ---
// Google endpoints documented by Google Identity docs. :contentReference[oaicite:1]{index=1}
const GOOGLE_CLIENT_ID     = 'YOUR_GOOGLE_CLIENT_ID';
const GOOGLE_CLIENT_SECRET = 'YOUR_GOOGLE_CLIENT_SECRET';

// GitHub OAuth flow documented by GitHub. :contentReference[oaicite:2]{index=2}
const GITHUB_CLIENT_ID     = 'YOUR_GITHUB_CLIENT_ID';
const GITHUB_CLIENT_SECRET = 'YOUR_GITHUB_CLIENT_SECRET';

// OAuth scopes
const GOOGLE_SCOPES = ['openid', 'email', 'profile'];
const GITHUB_SCOPES = ['read:user', 'user:email'];

// Session settings
const SESSION_NAME = 'pagewright_admin';

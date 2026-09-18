<?php
/**
 * Loaded first by every protected page: database, helpers and session.
 * Kept separate from header.php so a page can run its role check and any
 * redirects *before* the first byte of HTML is sent.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

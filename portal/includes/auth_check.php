<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in and has one of the specified roles.
 * @param array $roles Array of authorized user types (e.g., ['superadmin', 'staff']).
 * @return bool True if authorized, false otherwise.
 */
function is_authorized(array $roles = []): bool {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return false;
    }
    if (empty($roles)) {
        return true; // If no roles are specified, any logged-in user is considered authorized.
    }
    return in_array($_SESSION['user_type'], $roles, true);
}

/**
 * Redirects to the login page if the user is not authorized.
 * @param array $roles Array of authorized user types.
 */
function auth_guard(array $roles = []): void {
    if (!is_authorized($roles)) {
        $_SESSION['error_message'] = "You are not authorized to access this page.";
        header('Location: /nafconm/portal/login.php');
        exit();
    }
}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Agri-Logistics System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php include_once __DIR__.'/config/db.php'; echo BASE_URL; ?>css/style.css"> <style>
        /* Specific styles for dashboard layout */
        body {
            background-color: #f4f7f6;
            display: flex; /* Use flexbox for main layout */
        }
        #wrapper {
            display: flex;
            width: 100%;
        }
        .sidebar {
            width: 250px;
            min-width: 250px;
            height: 100vh;
            background-color: #343a40; /* Dark sidebar */
            color: #fff;
            padding-top: 20px;
            position: fixed; /* Keep sidebar fixed */
            left: 0;
            top: 0;
            overflow-y: auto; /* Enable scrolling for long content */
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 15px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }
        .content {
            flex-grow: 1; /* Allow content to take remaining space */
            margin-left: 250px; /* Offset for fixed sidebar */
            padding: 20px;
        }
        .navbar {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0,0,0,.1);
            position: sticky; /* Make navbar sticky at the top of content */
            top: 0;
            z-index: 1000; /* Ensure navbar stays on top */
        }
        .navbar-brand {
            font-weight: bold;
            color: #28a745 !important;
        }
    </style>
</head>
<body>
    <div id="wrapper">
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Agri-Logistics System'; ?></title>
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">

    <!-- Simple Dashboard Layout Styles -->
    <style>
        body { 
            background-color: #f4f8f4; 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0;
            padding: 0;
        }
        
        #wrapper { 
            display: flex; 
            width: 100%; 
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar { 
            width: 250px; 
            min-width: 250px; 
            height: 100vh; 
            background-color: #2d6a4f; 
            color: #fff; 
            padding-top: 20px; 
            position: fixed; 
            left: 0; 
            top: 0; 
            overflow-y: auto; 
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #1b4332;
            margin-bottom: 10px;
        }
        
        .sidebar-header h5 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
            color: #f1fdf0;
        }
        
        .sidebar-header p {
            margin: 0;
            font-size: 14px;
            color: #cbd5cb;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav .nav-item {
            margin: 0;
        }
        
        /* Main Content */
        .content { 
            flex: 1; 
            margin-left: 250px; 
            padding: 20px; 
            background-color: #f4f8f4; 
        }
        
        /* Navbar */
        .navbar { 
            background-color: #fff; 
            border-bottom: 1px solid #e9ecef; 
            padding: 10px 20px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.05); 
        }
        
        .dropdown-menu {
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 6px;
        }
        
        .dropdown-item {
            padding: 8px 16px;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #2d6a4f;
        }
        
        .navbar-brand { 
            font-weight: bold; 
            color: #2d6a4f !important; 
            font-size: 1.2rem; 
        }
        
        .nav-item .nav-link { 
            color: #444; 
        }
        
        .nav-item .nav-link:hover { 
            color: #2d6a4f; 
        }
        
        /* Container */
        .container-fluid {
            flex: 1;
            padding: 0;
        }
        
        /* Cards */
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Tables */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Buttons */
        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 6px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
                padding: 15px;
            }
            
            .navbar {
                padding: 10px 15px;
            }
        }
    </style>

    <!-- Sidebar Navigation Override Styles - Loaded after external CSS -->
    <style>
        /* Override external CSS file styles with maximum specificity */
        body .sidebar .sidebar-nav .nav-link { 
            color: #f1fdf0 !important; 
            padding: 12px 20px !important; 
            font-weight: 500 !important; 
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.3s ease !important;
            border-left: 3px solid transparent !important;
            background-color: transparent !important;
        }
        
        body .sidebar .sidebar-nav .nav-link i {
            width: 20px !important;
            margin-right: 10px !important;
            text-align: center !important;
        }
        
        body .sidebar .sidebar-nav .nav-link:hover { 
            color: #fff !important; 
            background-color: #1b4332 !important; 
            text-decoration: none !important;
            border-left-color: #4ade80 !important;
        }
        
        body .sidebar .sidebar-nav .nav-link.active { 
            color: #fff !important; 
            text-decoration: none !important;
            font-weight: 600 !important;
        }
        
        /* Activity Dot Indicator */
        body .sidebar .sidebar-nav .nav-link .activity-dot {
            width: 8px !important;
            height: 8px !important;
            background-color: #ff4757 !important;
            border-radius: 50% !important;
            display: inline-block !important;
            margin-left: 8px !important;
            animation: pulse 2s infinite !important;
            position: relative !important;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(255, 71, 87, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 71, 87, 0);
            }
        }
    </style>
</head>
<body>
    <div id="wrapper">

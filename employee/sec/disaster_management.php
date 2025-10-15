<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Get pending counts for nav badges
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$pending_complaints = mysqli_fetch_assoc($result)['pending'];

// Get resident counts per zone with demographic breakdown
$zone_data = [];
$zones_query = "SELECT 
    zone, 
    COUNT(*) as total_count,
    SUM(CASE WHEN age < 5 THEN 1 ELSE 0 END) as toddlers,
    SUM(CASE WHEN age BETWEEN 5 AND 12 THEN 1 ELSE 0 END) as children,
    SUM(CASE WHEN age BETWEEN 13 AND 17 THEN 1 ELSE 0 END) as teens,
    SUM(CASE WHEN age BETWEEN 18 AND 59 THEN 1 ELSE 0 END) as adults,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN LOWER(first_name) LIKE '%male%' OR LOWER(last_name) LIKE '%male%' THEN 1 ELSE 0 END) as males,
    SUM(CASE WHEN LOWER(first_name) NOT LIKE '%male%' AND LOWER(last_name) NOT LIKE '%male%' THEN 1 ELSE 0 END) as females
FROM residents 
WHERE status = 'active'
GROUP BY zone 
ORDER BY zone";
$zones_result = mysqli_query($connection, $zones_query);

if ($zones_result) {
    while ($row = mysqli_fetch_assoc($zones_result)) {
        $zone_data[$row['zone']] = [
            'total' => intval($row['total_count']),
            'toddlers' => intval($row['toddlers']),
            'children' => intval($row['children']),
            'teens' => intval($row['teens']),
            'adults' => intval($row['adults']),
            'elderly' => intval($row['elderly']),
            'males' => intval($row['males']),
            'females' => intval($row['females'])
        ];
    }
}

// Comprehensive disaster risk assessment
$zone_hazards = [
    'Zone 1A' => ['flood' => 'high', 'landslide' => 'low', 'earthquake' => 'medium', 'fire' => 'high', 'coastal' => 'medium'],
    'Zone 1B' => ['flood' => 'medium', 'landslide' => 'low', 'earthquake' => 'medium', 'fire' => 'high', 'coastal' => 'low'],
    'Zone 2A' => ['flood' => 'critical', 'landslide' => 'medium', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'high'],
    'Zone 2B' => ['flood' => 'high', 'landslide' => 'medium', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'medium'],
    'Zone 3A' => ['flood' => 'medium', 'landslide' => 'high', 'earthquake' => 'high', 'fire' => 'low', 'coastal' => 'low'],
    'Zone 3B' => ['flood' => 'low', 'landslide' => 'critical', 'earthquake' => 'high', 'fire' => 'low', 'coastal' => 'low'],
    'Zone 4A' => ['flood' => 'high', 'landslide' => 'medium', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'medium'],
    'Zone 4B' => ['flood' => 'medium', 'landslide' => 'high', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'low'],
    'Zone 5A' => ['flood' => 'critical', 'landslide' => 'low', 'earthquake' => 'medium', 'fire' => 'high', 'coastal' => 'critical'],
    'Zone 5B' => ['flood' => 'high', 'landslide' => 'low', 'earthquake' => 'medium', 'fire' => 'high', 'coastal' => 'high'],
    'Zone 6A' => ['flood' => 'medium', 'landslide' => 'medium', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'medium'],
    'Zone 6B' => ['flood' => 'low', 'landslide' => 'high', 'earthquake' => 'high', 'fire' => 'low', 'coastal' => 'low'],
    'Zone 7A' => ['flood' => 'high', 'landslide' => 'medium', 'earthquake' => 'medium', 'fire' => 'medium', 'coastal' => 'medium'],
    'Zone 7B' => ['flood' => 'medium', 'landslide' => 'critical', 'earthquake' => 'high', 'fire' => 'low', 'coastal' => 'low']
];

// Zone boundaries positioned on LAND within Barangay Cawit
$zone_boundaries = [
    'Zone 1A' => [
        [6.9720, 121.9720],
        [6.9720, 121.9755],
        [6.9708, 121.9758],
        [6.9695, 121.9750],
        [6.9695, 121.9715],
        [6.9708, 121.9718]
    ],
    'Zone 1B' => [
        [6.9720, 121.9755],
        [6.9720, 121.9790],
        [6.9708, 121.9795],
        [6.9695, 121.9785],
        [6.9695, 121.9750],
        [6.9708, 121.9758]
    ],
    'Zone 2A' => [
        [6.9695, 121.9715],
        [6.9695, 121.9750],
        [6.9680, 121.9755],
        [6.9668, 121.9745],
        [6.9668, 121.9710],
        [6.9680, 121.9713]
    ],
    'Zone 2B' => [
        [6.9695, 121.9750],
        [6.9695, 121.9785],
        [6.9680, 121.9792],
        [6.9668, 121.9780],
        [6.9668, 121.9745],
        [6.9680, 121.9755]
    ],
    'Zone 3A' => [
        [6.9668, 121.9710],
        [6.9668, 121.9745],
        [6.9652, 121.9750],
        [6.9640, 121.9738],
        [6.9640, 121.9705],
        [6.9652, 121.9708]
    ],
    'Zone 3B' => [
        [6.9668, 121.9745],
        [6.9668, 121.9780],
        [6.9652, 121.9788],
        [6.9640, 121.9773],
        [6.9640, 121.9738],
        [6.9652, 121.9750]
    ],
    'Zone 4A' => [
        [6.9640, 121.9705],
        [6.9640, 121.9738],
        [6.9625, 121.9745],
        [6.9612, 121.9732],
        [6.9612, 121.9700],
        [6.9625, 121.9703]
    ],
    'Zone 4B' => [
        [6.9640, 121.9738],
        [6.9640, 121.9773],
        [6.9625, 121.9782],
        [6.9612, 121.9767],
        [6.9612, 121.9732],
        [6.9625, 121.9745]
    ],
    'Zone 5A' => [
        [6.9612, 121.9700],
        [6.9612, 121.9732],
        [6.9595, 121.9740],
        [6.9582, 121.9725],
        [6.9582, 121.9695],
        [6.9595, 121.9698]
    ],
    'Zone 5B' => [
        [6.9612, 121.9732],
        [6.9612, 121.9767],
        [6.9595, 121.9777],
        [6.9582, 121.9760],
        [6.9582, 121.9725],
        [6.9595, 121.9740]
    ],
    'Zone 6A' => [
        [6.9582, 121.9695],
        [6.9582, 121.9725],
        [6.9565, 121.9735],
        [6.9552, 121.9720],
        [6.9552, 121.9690],
        [6.9565, 121.9693]
    ],
    'Zone 6B' => [
        [6.9582, 121.9725],
        [6.9582, 121.9760],
        [6.9565, 121.9772],
        [6.9552, 121.9755],
        [6.9552, 121.9720],
        [6.9565, 121.9735]
    ],
    'Zone 7A' => [
        [6.9552, 121.9690],
        [6.9552, 121.9720],
        [6.9535, 121.9730],
        [6.9522, 121.9715],
        [6.9522, 121.9685],
        [6.9535, 121.9688]
    ],
    'Zone 7B' => [
        [6.9552, 121.9720],
        [6.9552, 121.9755],
        [6.9535, 121.9768],
        [6.9522, 121.9750],
        [6.9522, 121.9715],
        [6.9535, 121.9730]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Management - Cawit Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand i {
            margin-right: 12px;
            font-size: 1.5rem;
            color: #3498db;
        }

        .user-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .user-name {
            font-weight: bold;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.8;
            color: #3498db;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bdc3c7;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-left-color: #3498db;
        }

        .nav-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left-color: #3498db;
        }

        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: auto;
            float: right;
        }

        .logout-section {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
            margin-top: auto;
        }

        .logout-btn {
            width: 100%;
            background: rgba(231, 76, 60, 0.2);
            color: white;
            border: 1px solid rgba(231, 76, 60, 0.5);
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            border-color: #e74c3c;
            transform: translateY(-1px);
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
        }

        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1rem;
            gap: 1rem;
        }

        .map-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 600px;
            position: relative;
        }

        .map-header {
            padding: 1rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .map-header h2 {
            font-size: 1.2rem;
            margin: 0;
        }

        .map-controls {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .control-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .control-btn.primary {
            background: white;
            color: #3498db;
        }

        .control-btn.primary:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }

        #map {
            flex: 1;
            min-height: 500px;
            z-index: 1;
            position: relative;
        }

        .map-legend-overlay {
            position: absolute;
            top: 80px;
            right: 10px;
            background: rgba(255, 255, 255, 0.98);
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 250px;
            backdrop-filter: blur(10px);
            border: 2px solid #e9ecef;
        }

        .map-legend-title {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.75rem;
        }

        .map-legend-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .map-legend-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .map-legend-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid rgba(0,0,0,0.2);
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .zone-popup {
            min-width: 320px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .zone-popup-header {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: -1.25rem -1.25rem 1rem -1.25rem;
            padding: 1rem 1.25rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .demographics-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .demographics-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #495057;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.5px;
        }

        .demographics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .demographic-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            padding: 0.6rem;
            background: white;
            border-radius: 8px;
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .demographic-item:hover {
            transform: translateX(3px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        .demographic-item.toddler {
            border-left-color: #e83e8c;
        }

        .demographic-item.child {
            border-left-color: #fd7e14;
        }

        .demographic-item.teen {
            border-left-color: #6f42c1;
        }

        .demographic-item.adult {
            border-left-color: #20c997;
        }

        .demographic-item.elderly {
            border-left-color: #6c757d;
        }

        .demographic-icon {
            font-size: 1.3rem;
        }

        .demographic-count {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .gender-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .gender-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem;
            background: white;
            border-radius: 8px;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-weight: 500;
        }

        .gender-item strong {
            font-size: 1.1rem;
        }

        .gender-item.male {
            border-left: 3px solid #007bff;
        }

        .gender-item.female {
            border-left: 3px solid #e83e8c;
        }

        .zone-popup-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .zone-popup-stat {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #dee2e6;
        }

        .zone-popup-stat i {
            font-size: 1.5rem;
        }

        .zone-popup-stat strong {
            font-size: 1.1rem;
            display: block;
        }

        .zone-popup-hazards {
            margin: 0.75rem 0;
        }

        .hazard-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #495057;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.5px;
        }

        .hazard-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .hazard-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 0.6rem 0.75rem;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-weight: 500;
        }

        .hazard-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .hazard-badge.critical {
            background: #dc3545;
            color: white;
        }

        .hazard-badge.high {
            background: #fd7e14;
            color: white;
        }

        .hazard-badge.medium {
            background: #ffc107;
            color: #333;
        }

        .hazard-badge.low {
            background: #28a745;
            color: white;
        }

        .zone-popup-danger {
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-top: 1rem;
            text-transform: uppercase;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            letter-spacing: 1px;
        }

        .danger-critical {
            background: #dc3545;
            color: white;
        }

        .danger-high {
            background: #fd7e14;
            color: white;
        }

        .danger-medium {
            background: #ffc107;
            color: #333;
        }

        .danger-low {
            background: #28a745;
            color: white;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            border: 2px solid #e9ecef;
        }

        .leaflet-popup-content {
            margin: 1.25rem;
            font-size: 0.95rem;
        }

        .leaflet-popup-close-button {
            font-size: 24px !important;
            padding: 8px 12px !important;
            color: #999 !important;
        }

        .leaflet-popup-close-button:hover {
            color: #333 !important;
        }

        .zone-polygon {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .zone-polygon:hover {
            filter: brightness(1.1);
        }

        .zone-label {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #333;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: bold;
            color: #000;
            text-align: center;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            pointer-events: none;
            backdrop-filter: blur(4px);
            line-height: 1.3;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .content-area {
                padding: 0.5rem;
            }

            .map-container {
                min-height: 400px;
            }

            .map-legend-overlay {
                top: 70px;
                right: 5px;
                max-width: 170px;
                padding: 0.75rem;
            }

            .zone-popup {
                min-width: 240px;
            }
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="fas fa-building"></i>
                Cawit Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Secretary</div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Resident Management</div>
                <a href="resident-profiling.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    Resident Profiling
                </a>
                <a href="resident_family.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Resident Family
                </a>
                <a href="resident_account.php" class="nav-item">
                    <i class="fas fa-user-shield"></i>
                    Resident Accounts
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Service Management</div>
                <a href="community_service.php" class="nav-item">
                    <i class="fas fa-hands-helping"></i>
                    Community Service
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Announcements
                </a>
                <a href="complaints.php" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints
                    <?php if ($pending_complaints > 0): ?>
                        <span class="nav-badge"><?php echo $pending_complaints; ?></span>
                    <?php endif; ?>
                </a>
                <a href="certificates.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Certificates
                </a>
                <a href="disaster_management.php" class="nav-item active">
                    <i class="fas fa-house-damage"></i>
                    Disaster Management
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </div>

        <div class="logout-section">
            <form action="../../employee/logout.php" method="POST" id="logoutForm" style="width: 100%;">
                <button type="button" class="logout-btn" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </form>
        </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Disaster Management - Multi-Hazard Risk Assessment</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <div class="map-container">
                <div class="map-header">
                    <h2><i class="fas fa-map-marked-alt"></i> Barangay Cawit - Multi-Hazard Zone Assessment</h2>
                    <div class="map-controls">
                        <button class="control-btn primary" onclick="centerMap()">
                            <i class="fas fa-crosshairs"></i> Center Map
                        </button>
                        <button class="control-btn primary" onclick="highlightAllZones()">
                            <i class="fas fa-layer-group"></i> Highlight All
                        </button>
                    </div>
                </div>
                <div id="map"></div>
                
                <div class="map-legend-overlay">
                    <div class="map-legend-title">
                        <i class="fas fa-shield-alt"></i>
                        Overall Danger Level
                    </div>
                    <div class="map-legend-items">
                        <div class="map-legend-item">
                            <div class="map-legend-color" style="background: rgba(220, 53, 69, 0.6);"></div>
                            <span><strong>Critical</strong> - Extreme Risk</span>
                        </div>
                        <div class="map-legend-item">
                            <div class="map-legend-color" style="background: rgba(253, 126, 20, 0.6);"></div>
                            <span><strong>High</strong> - Major Risk</span>
                        </div>
                        <div class="map-legend-item">
                            <div class="map-legend-color" style="background: rgba(255, 193, 7, 0.6);"></div>
                            <span><strong>Medium</strong> - Moderate Risk</span>
                        </div>
                        <div class="map-legend-item">
                            <div class="map-legend-color" style="background: rgba(40, 167, 69, 0.6);"></div>
                            <span><strong>Low</strong> - Minor Risk</span>
                        </div>
                    </div>
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e9ecef; font-size: 0.7rem; color: #666;">
                        <div style="margin-bottom: 0.3rem;"><strong>Factors:</strong></div>
                        <div style="line-height: 1.6;">🌊 Flood Risk</div>
                        <div style="line-height: 1.6;">⛰️ Landslide Risk</div>
                        <div style="line-height: 1.6;">🏚️ Earthquake Risk</div>
                        <div style="line-height: 1.6;">🔥 Fire Risk</div>
                        <div style="line-height: 1.6;">🌊 Coastal Hazard</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const zoneData = <?php echo json_encode($zone_data); ?>;
        const zoneBoundaries = <?php echo json_encode($zone_boundaries); ?>;
        const zoneHazards = <?php echo json_encode($zone_hazards); ?>;

        let map;
        let zonePolygons = {};

        const cawitBoundary = [
            [6.97879956774351, 121.95418526512384],
            [6.97879956774351, 121.99418526512384],
            [6.93879956774351, 121.99418526512384],
            [6.93879956774351, 121.95418526512384],
            [6.97879956774351, 121.95418526512384]
        ];

        const worldOuter = [
            [90, -180],
            [90, 180],
            [-90, 180],
            [-90, -180],
            [90, -180]
        ];

        function calculateDangerLevel(hazards, population) {
            const scores = {'low': 1, 'medium': 2, 'high': 3, 'critical': 4};
            let total = 0;
            let count = 0;
            
            for (let hazard in hazards) {
                total += scores[hazards[hazard]];
                count++;
            }
            
            let average = total / count;
            
            if (population >= 100) average += 1.0;
            else if (population >= 50) average += 0.5;
            else if (population >= 20) average += 0.25;
            
            if (average >= 3.5) return 'critical';
            if (average >= 2.8) return 'high';
            if (average >= 2.0) return 'medium';
            return 'low';
        }

        function getDangerColor(danger) {
            const colors = {
                'critical': 'rgba(220, 53, 69, 0.6)',
                'high': 'rgba(253, 126, 20, 0.6)',
                'medium': 'rgba(255, 193, 7, 0.6)',
                'low': 'rgba(40, 167, 69, 0.6)'
            };
            return colors[danger] || 'rgba(108, 117, 125, 0.6)';
        }

        function getDangerBorderColor(danger) {
            const colors = {
                'critical': '#dc3545',
                'high': '#fd7e14',
                'medium': '#ffc107',
                'low': '#28a745'
            };
            return colors[danger] || '#6c757d';
        }

        function getHazardIcon(hazardType) {
            const icons = {
                'flood': '🌊',
                'landslide': '⛰️',
                'earthquake': '🏚️',
                'fire': '🔥',
                'coastal': '🌊'
            };
            return icons[hazardType] || '⚠️';
        }

        function getHazardLabel(hazardType) {
            const labels = {
                'flood': 'Flood',
                'landslide': 'Landslide',
                'earthquake': 'Earthquake',
                'fire': 'Fire',
                'coastal': 'Coastal'
            };
            return labels[hazardType] || hazardType;
        }

        function initMap() {
            const cawitBounds = L.latLngBounds(
                [6.93879956774351, 121.95418526512384], // Southwest corner
                [6.97879956774351, 121.99418526512384]  // Northeast corner
            );

            map = L.map('map', {
                zoomControl: true,
                attributionControl: true,
                maxZoom: 18,
                minZoom: 16,
                maxBounds: cawitBounds,
                maxBoundsViscosity: 1.0
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            setTimeout(() => {
                map.invalidateSize();

                const boundaryPolygon = L.polygon(cawitBoundary, {
                    color: '#FF4136',
                    weight: 3,
                    fillOpacity: 0,
                    fillColor: 'transparent'
                }).addTo(map);

                const bounds = boundaryPolygon.getBounds();
                map.fitBounds(bounds, { 
                    padding: [30, 30],
                    maxZoom: 16
                });

                map.createPane('maskPane');
                const maskPane = map.getPane('maskPane');
                maskPane.style.zIndex = 450;
                maskPane.style.pointerEvents = 'none';

                L.polygon([worldOuter, cawitBoundary], {
                    pane: 'maskPane',
                    stroke: false,
                    fillColor: '#000000',
                    fillOpacity: 0.85,
                    interactive: false
                }).addTo(map);

                Object.keys(zoneBoundaries).forEach(zoneName => {
                    const boundary = zoneBoundaries[zoneName];
                    const zoneInfo = zoneData[zoneName] || {
                        total: 0,
                        toddlers: 0,
                        children: 0,
                        teens: 0,
                        adults: 0,
                        elderly: 0,
                        males: 0,
                        females: 0
                    };
                    const count = zoneInfo.total;
                    const hazards = zoneHazards[zoneName] || {};
                    const danger = calculateDangerLevel(hazards, count);
                    const color = getDangerColor(danger);
                    const borderColor = getDangerBorderColor(danger);

                    const polygon = L.polygon(boundary, {
                        color: borderColor,
                        weight: 4,
                        fillColor: color,
                        fillOpacity: 0.7,
                        className: 'zone-polygon'
                    }).addTo(map);

                    let hazardListHTML = '';
                    for (let hazardType in hazards) {
                        const level = hazards[hazardType];
                        hazardListHTML += `
                            <div class="hazard-item">
                                <span>${getHazardIcon(hazardType)} ${getHazardLabel(hazardType)}</span>
                                <span class="hazard-badge ${level}">${level}</span>
                            </div>
                        `;
                    }

                    const vulnerable = zoneInfo.toddlers + zoneInfo.children + zoneInfo.elderly;

                    const popupContent = `
                        <div class="zone-popup">
                            <div class="zone-popup-header">
                                <i class="fas fa-map-marker-alt"></i>
                                ${zoneName}
                            </div>
                            
                            <div class="demographics-section">
                                <div class="demographics-title">
                                    <i class="fas fa-users"></i>
                                    Population Demographics
                                </div>
                                <div class="demographics-grid">
                                    <div class="demographic-item toddler">
                                        <span class="demographic-icon">👶</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Toddlers (0-4)</div>
                                            <div class="demographic-count">${zoneInfo.toddlers}</div>
                                        </div>
                                    </div>
                                    <div class="demographic-item child">
                                        <span class="demographic-icon">🧒</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Children (5-12)</div>
                                            <div class="demographic-count">${zoneInfo.children}</div>
                                        </div>
                                    </div>
                                    <div class="demographic-item teen">
                                        <span class="demographic-icon">🧑</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Teens (13-17)</div>
                                            <div class="demographic-count">${zoneInfo.teens}</div>
                                        </div>
                                    </div>
                                    <div class="demographic-item adult">
                                        <span class="demographic-icon">👨</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Adults (18-59)</div>
                                            <div class="demographic-count">${zoneInfo.adults}</div>
                                        </div>
                                    </div>
                                    <div class="demographic-item elderly">
                                        <span class="demographic-icon">👴</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Elderly (60+)</div>
                                            <div class="demographic-count">${zoneInfo.elderly}</div>
                                        </div>
                                    </div>
                                    <div class="demographic-item" style="border-left-color: #dc3545;">
                                        <span class="demographic-icon">⚠️</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; color: #666;">Vulnerable</div>
                                            <div class="demographic-count">${vulnerable}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="gender-stats">
                                    <div class="gender-item male">
                                        <span><i class="fas fa-mars"></i> Male</span>
                                        <strong>${zoneInfo.males}</strong>
                                    </div>
                                    <div class="gender-item female">
                                        <span><i class="fas fa-venus"></i> Female</span>
                                        <strong>${zoneInfo.females}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="zone-popup-stats">
                                <div class="zone-popup-stat">
                                    <i class="fas fa-users" style="color: #3498db;"></i>
                                    <div>
                                        <strong>${count}</strong><br>
                                        <span style="font-size: 0.75rem; color: #666;">Total Residents</span>
                                    </div>
                                </div>
                                <div class="zone-popup-stat">
                                    <i class="fas fa-home" style="color: #27ae60;"></i>
                                    <div>
                                        <strong>~${Math.ceil(count / 4)}</strong><br>
                                        <span style="font-size: 0.75rem; color: #666;">Est. Households</span>
                                    </div>
                                </div>
                            </div>

                            <div class="zone-popup-hazards">
                                <div class="hazard-title">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Hazard Assessment
                                </div>
                                <div class="hazard-list">
                                    ${hazardListHTML}
                                </div>
                            </div>
                            <div class="zone-popup-danger danger-${danger}">
                                <i class="fas fa-shield-alt"></i>
                                OVERALL: ${danger.toUpperCase()} DANGER
                            </div>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e9ecef; font-size: 0.75rem; color: #666;">
                                <i class="fas fa-info-circle"></i> Vulnerable groups require priority evacuation assistance
                            </div>
                        </div>
                    `;

                    polygon.bindPopup(popupContent, {
                        maxWidth: 380,
                        minWidth: 320,
                        className: 'custom-popup',
                        closeButton: true,
                        autoClose: false,
                        closeOnClick: false
                    });

                    let hoverTimeout;
                    
                    polygon.on('mouseover', function(e) {
                        clearTimeout(hoverTimeout);
                        this.setStyle({
                            weight: 6,
                            fillOpacity: 0.9
                        });
                        if (!this.isPopupOpen()) {
                            this.bringToFront();
                        }
                    });

                    polygon.on('mouseout', function(e) {
                        hoverTimeout = setTimeout(() => {
                            if (!this.isPopupOpen()) {
                                this.setStyle({
                                    weight: 4,
                                    fillOpacity: 0.7
                                });
                            }
                        }, 100);
                    });

                    polygon.on('click', function(e) {
                        L.DomEvent.stopPropagation(e);
                        this.openPopup();
                        this.setStyle({
                            weight: 6,
                            fillOpacity: 0.9
                        });
                    });

                    polygon.on('popupclose', function() {
                        this.setStyle({
                            weight: 4,
                            fillOpacity: 0.7
                        });
                    });

                    const center = polygon.getBounds().getCenter();
                    const label = L.marker(center, {
                        icon: L.divIcon({
                            className: 'zone-label',
                            html: `
                                <div style="font-weight: bold; font-size: 0.75rem; text-shadow: 1px 1px 2px rgba(255,255,255,0.8);">${zoneName}</div>
                                <div style="font-size: 0.65rem; color: #333; font-weight: 600; margin-top: 1px;">${count}</div>
                            `,
                            iconSize: [65, 40],
                            iconAnchor: [32, 20]
                        })
                    }).addTo(map);

                    zonePolygons[zoneName] = { polygon, label };
                });

            }, 100);
        }

        function centerMap() {
            const boundaryPolygon = L.polygon(cawitBoundary);
            map.fitBounds(boundaryPolygon.getBounds(), { 
                padding: [30, 30],
                maxZoom: 16
            });
        }

        function highlightAllZones() {
            let delay = 0;
            Object.values(zonePolygons).forEach(({ polygon }) => {
                setTimeout(() => {
                    polygon.setStyle({
                        weight: 5,
                        fillOpacity: 0.9
                    });
                    
                    setTimeout(() => {
                        polygon.setStyle({
                            weight: 3,
                            fillOpacity: 0.6
                        });
                    }, 500);
                }, delay);
                delay += 100;
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function handleLogout() {
            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logoutForm').submit();
                }
            });
        }

        window.addEventListener('load', initMap);

        window.addEventListener('resize', function() {
            if (map) {
                setTimeout(() => {
                    map.invalidateSize();
                }, 200);
            }
        });
    </script>
</body>
</html>
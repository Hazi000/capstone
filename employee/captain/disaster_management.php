<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    header("Location: ../index.php");
    exit();
}

// Get pending counts for nav badges
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$pending_complaints = mysqli_fetch_assoc($result)['pending'];

$appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $appointment_query);
$pending_appointments = mysqli_fetch_assoc($result)['pending'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Management - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <!-- Replace Google Maps with Leaflet -->
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

        /* Sidebar Styles */
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            padding: 2rem;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        /* Map Styles - make map rectangle fill more of the viewport */
        .map-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 0;                         /* remove inner padding so map fills container */
            margin: 0 0 1rem 0;
            height: calc(100vh - 120px);        /* larger rectangle: leaves room for top bar */
            display: flex;
            flex-direction: column;
        }

        #map {
            width: 100%;
            height: 100%; /* fill container */
            border-radius: 0; /* remove rounding to maximize usable area */
            z-index: 1;
        }

        .leaflet-popup-content {
            padding: 10px;
        }

        .leaflet-popup-content h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }

        .leaflet-popup-content p {
            margin: 2px 0;
            font-size: 12px;
        }

        /* Mobile Responsiveness */
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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="fas fa-building"></i>
                Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Captain</div>
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

            <!-- Finance -->
            <div class="nav-section">
                <div class="nav-section-title">Finance</div>
                <a href="budgets.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    Budgets
                </a>
                <a href="expenses.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Expenses
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Disaster Management</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <!-- Page Content -->
        <div class="content-area">
            <div class="map-container">
                <div class="map-header">
                    <h2>Cawit, Zamboanga City Map</h2>
                    <div class="map-controls">
                        <button class="control-btn primary" onclick="centerMap()">
                            <i class="fas fa-crosshairs"></i> Center Map
                        </button>
                        <button class="control-btn danger" onclick="clearMarkers()">
                            <i class="fas fa-trash"></i> Clear Markers
                        </button>
                    </div>
                </div>
                <div id="map"></div>
            </div>
        </div>
    </div>

    <script>
        // --- map: strictly show the entire Barangay Cawit and make it fill the rectangle ---
        let map;
        let markers = [];

        // Center provided: 6.95879956774351, 121.97418526512384
        // Larger polygon around the provided center to cover the full barangay area
        // (adjust deltas if you have the official boundary coordinates)
        const cawitBoundary = [
            [6.97879956774351, 121.95418526512384], // NW
            [6.97879956774351, 121.99418526512384], // NE
            [6.93879956774351, 121.99418526512384], // SE
            [6.93879956774351, 121.95418526512384], // SW
            [6.97879956774351, 121.95418526512384]  // close polygon
        ];

        // world outer ring (used as outer ring for mask polygon)
        const worldOuter = [
            [90, -180],
            [90, 180],
            [-90, 180],
            [-90, -180]
        ];

        function initMap() {
            // initialize map
            map = L.map('map', {
                zoomControl: false,  // disable zoom controls
                attributionControl: true,
                maxZoom: 19,
                dragging: true,
                scrollWheelZoom: false,  // disable mouse wheel zoom
                doubleClickZoom: false,  // disable double click zoom
                touchZoom: false,        // disable touch zoom
                boxZoom: false,          // disable box zoom
                keyboard: false          // disable keyboard zoom
            });

            // base tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Ensure container layout applied, then size map and fit polygon so it fills the rectangle
            setTimeout(() => {
                map.invalidateSize();

                // visible boundary stroke
                const boundaryPolygon = L.polygon(cawitBoundary, {
                    color: '#FF4136',
                    weight: 2,
                    fillOpacity: 0
                }).addTo(map);

                const bounds = boundaryPolygon.getBounds();

                // Fit bounds first (no padding)
                map.fitBounds(bounds, { padding: [0, 0], animate: false });

                // Recalculate and compute the exact zoom that fills the rectangle,
                // then set view to center at that zoom and lock minZoom to prevent zooming out.
                setTimeout(() => {
                    map.invalidateSize();
                    // compute best-fit zoom to fill the bounds tightly, then increase to start zoomed in
                    const bestZoom = map.getBoundsZoom(bounds, false);
                    // start zoomed in by +2
                    const increasedZoom = Math.min(bestZoom + 2, map.getMaxZoom());
                    map.setView(bounds.getCenter(), increasedZoom, { animate: false });
                    map.setMinZoom(increasedZoom); // prevent zooming OUT from initial view
                    map.options.minZoom = increasedZoom;
                    map.setMaxBounds(bounds.pad(0.01));
                    map.options.maxBoundsViscosity = 1.0;

                    // Enforce minZoom at all times (user cannot zoom out even after zooming in)
                    map.on('zoomend', function () {
                        if (map.getZoom() < increasedZoom) {
                            map.setZoom(increasedZoom);
                        }
                    });
                }, 120);

                // Create mask pane and mask polygon (world minus Cawit)
                map.createPane('maskPane');
                const maskPane = map.getPane('maskPane');
                maskPane.style.zIndex = 450;
                maskPane.style.pointerEvents = 'none';

                L.polygon([ worldOuter, cawitBoundary ], {
                    pane: 'maskPane',
                    stroke: false,
                    color: '#ffffff',
                    fillColor: '#ffffff',
                    fillOpacity: 1,
                    interactive: false
                }).addTo(map);

                // initial center marker
                addMarker(bounds.getCenter(), 'Barangay Cawit Center', true);

                // clicks only inside boundary
                map.on('click', function(e) {
                    if (isLatLngInPolygon(e.latlng, cawitBoundary)) {
                        addMarker(e.latlng);
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Outside Barangay',
                            text: 'Markers can only be placed within Barangay Cawit.'
                        });
                    }
                });

                // snap back if center moves out
                map.on('moveend', () => {
                    const maxBounds = map.getMaxBounds();
                    if (!maxBounds.contains(map.getCenter())) {
                        map.panInsideBounds(maxBounds, { animate: true });
                    }
                });
            }, 80);
        }

        // ray-casting point-in-polygon (lng/x, lat/y)
        function isLatLngInPolygon(latlng, polygon) {
            const x = latlng.lng, y = latlng.lat;
            let inside = false;
            for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                const xi = polygon[i][1], yi = polygon[i][0];
                const xj = polygon[j][1], yj = polygon[j][0];
                const intersect = ((yi > y) !== (yj > y)) &&
                    (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
                if (intersect) inside = !inside;
            }
            return inside;
        }

        function addMarker(latlng, title = 'Marked Location', center = false) {
            const marker = L.marker(latlng, { title: title, draggable: true }).addTo(map);
            markers.push(marker);

            marker.bindPopup(`
                <div>
                    <h3>${title}</h3>
                    <p>Lat: ${latlng.lat.toFixed(6)}</p>
                    <p>Lng: ${latlng.lng.toFixed(6)}</p>
                </div>
            `);

            marker.on('click', () => marker.openPopup());

            if (center) {
                map.setView(latlng, map.getZoom(), { animate: true });
            }
        }

        function clearMarkers() {
            markers.forEach(m => map.removeLayer(m));
            markers = [];
        }

        function centerMap() {
            const boundary = L.polygon(cawitBoundary);
            map.fitBounds(boundary.getBounds());
        }

        // Initialize map when page loads
        window.addEventListener('load', initMap);
    </script>

    <script>
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
    </script>
</body>
</html>
    </script>

    <script>
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
    </script>
</body>
</html>

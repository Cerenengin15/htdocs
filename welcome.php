<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: /login.php");
    exit();
}

include 'connection.php';

// Girilen email adresine göre veritabanındaki ID ve parentID verilerini getirir.
$email = $_SESSION['email'];
$sqlUser = "SELECT avatar, ID, parentID, Name FROM user WHERE email = :email";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bindParam(':email', $email);
$stmtUser->execute();
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);
$userAvatar =$user['avatar'];
$userID = $user['ID'];
$parentID = $user['parentID'];
$userName = $user['Name'];

// Kullanıcının onaylaması gereken istekleri getirir.
$sqlPending = "SELECT r.requestID, r.Request, r.Comment, r.tarih  
               FROM requests r 
               JOIN user u ON r.approverID = u.ID 
               WHERE r.approverID = :userID 
               AND r.status = 'pending'";
$stmtPending = $conn->prepare($sqlPending);
$stmtPending->bindParam(':userID', $userID);
$stmtPending->execute();
$pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının onaylanan isteklerini getirir.
$sqlApproved = "SELECT requestID, Request, Comment, tarih FROM requests WHERE userID = :userID AND status = 'approved'";
$stmtApproved = $conn->prepare($sqlApproved);
$stmtApproved->bindParam(':userID', $userID);
$stmtApproved->execute();
$approvedRequests = $stmtApproved->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının bekleyen isteklerini getirir.
$sqlWaiting = "SELECT requestID, Request, Comment, tarih FROM requests WHERE userID = :userID AND status = 'pending' AND approverID IS NOT NULL";
$stmtWaiting = $conn->prepare($sqlWaiting);
$stmtWaiting->bindParam(':userID', $userID);
$stmtWaiting->execute();
$waitingRequests = $stmtWaiting->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının tüm isteklerini getirir.
$sqlAll = "SELECT requestID, Request, Comment, tarih, status FROM requests WHERE userID = :userID";
$stmtAll = $conn->prepare($sqlAll);
$stmtAll->bindParam(':userID', $userID);
$stmtAll->execute();
$allRequests = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request'], $_POST['comment'])) {
    $newRequest = $_POST['request'];
    $newComment = $_POST['comment'];
    $newDate = date('Y-m-d'); // Bugünün tarihini almak için

    // Yeni istek ekleme. Status otomatik olarak pending olacak
    try {
        $addSql = "INSERT INTO requests (userID, approverID, Request, Comment, tarih, status) VALUES (:userID, :approverID, :request, :comment, :date, 'pending')";
        $addStmt = $conn->prepare($addSql);
        $addStmt->bindParam(':userID', $userID);
        $addStmt->bindParam(':approverID', $parentID);
        $addStmt->bindParam(':request', $newRequest);
        $addStmt->bindParam(':comment', $newComment);
        $addStmt->bindParam(':date', $newDate);
        $addStmt->execute();
        $_SESSION['add_success'] = true; // Sisteme yeni eklenen istek için başarı mesajı
        header("Location: welcome.php");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request_id'])) {
    $approveRequestID = $_POST['approve_request_id'];

    // İstek bilgilerinin alınması
    $sqlRequest = "SELECT userID, approverID FROM requests WHERE requestID = :requestID";
    $stmtRequest = $conn->prepare($sqlRequest);
    $stmtRequest->bindParam(':requestID', $approveRequestID);
    $stmtRequest->execute();
    $request = $stmtRequest->fetch(PDO::FETCH_ASSOC);
    $initialUserID = $request['userID'];
    $currentApproverID = $request['approverID'];

    // Şu anki kullanıcının ebeveynini belirleme
    $sqlParent = "SELECT parentID FROM user WHERE ID = :userID";
    $stmtParent = $conn->prepare($sqlParent);
    $stmtParent->bindParam(':userID', $currentApproverID);
    $stmtParent->execute();
    $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
    $nextParentID = $parent['parentID'];

    // Eğer en üst ebeveyne ulaşıldıysa, isteği onayla
    if ($nextParentID == 0) {
        $approveSql = "UPDATE requests SET status = 'approved', approverID = NULL WHERE requestID = :requestID";
        $approveStmt = $conn->prepare($approveSql);
        $approveStmt->bindParam(':requestID', $approveRequestID);
        $approveStmt->execute();
        $_SESSION['request_approved'] = true;
    } else {
        // İsteğin bir sonraki onaylayıcısını güncelle
        $updateRequestSql = "UPDATE requests SET approverID = :nextParentID WHERE requestID = :requestID";
        $updateRequestStmt = $conn->prepare($updateRequestSql);
        $updateRequestStmt->bindParam(':nextParentID', $nextParentID);
        $updateRequestStmt->bindParam(':requestID', $approveRequestID);
        $updateRequestStmt->execute();
    }

    header("Location: welcome.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="sm" data-bs-theme="dark" data-body-image="img-1" data-preloader="disable" data-sidebar-visibility="show" data-layout-style="default" data-layout-width="fluid" data-layout-position="fixed">
<head>
<meta charset="utf-8" />
    <title>İsteklerim</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Themesbrand" name="author" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">

    <!-- jsvectormap css -->
    <link href="assets/libs/jsvectormap/css/jsvectormap.min.css" rel="stylesheet" type="text/css" />

    <!--Swiper slider css-->
    <link href="assets/libs/swiper/swiper-bundle.min.css" rel="stylesheet" type="text/css" />

    <!-- Layout config Js -->
    <script src="assets/js/layout.js"></script>
    <!-- Bootstrap Css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <!-- custom Css-->
    <link href="assets/css/custom.min.css" rel="stylesheet" type="text/css" />

</head>
<body>
<div id="layout-wrapper">
    <header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="welcome.php" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="assets/images/logo-dark.png" alt="" height="17">
                        </span>
                    </a>

                    <a href="welcome.php" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="assets/images/logo-light.png" alt="" height="17">
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger" id="topnav-hamburger-icon">
                <span class="hamburger-icon open">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>

                <!-- App Search-->
                <form class="app-search d-none d-md-block">
                <div class="position-relative">
                    <input type="text" class="form-control" placeholder="Search..." autocomplete="off" id="search-options" value="">
                    <span class="mdi mdi-magnify search-widget-icon"></span>
                    <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none" id="search-close-options"></span>
                </div>
                <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown">
                    <div data-simplebar="init" style="max-height: 320px;"><div class="simplebar-wrapper" style="margin: 0px;"><div class="simplebar-height-auto-observer-wrapper"><div class="simplebar-height-auto-observer"></div></div><div class="simplebar-mask"><div class="simplebar-offset" style="right: 0px; bottom: 0px;"><div class="simplebar-content-wrapper" tabindex="0" role="region" aria-label="scrollable content" style="height: auto; overflow: hidden;"><div class="simplebar-content" style="padding: 0px;">
                        <!-- item-->
                        <div class="dropdown-header">
                            <h6 class="text-overflow text-muted mb-0 text-uppercase">Recent Searches</h6>
                        </div>

                        <div class="dropdown-item bg-transparent text-wrap">
                            <a href="welcome.php" class="btn btn-soft-primary btn-sm rounded-pill">how to setup <i class="mdi mdi-magnify ms-1"></i></a>
                            <a href="welcome.php" class="btn btn-soft-primary btn-sm rounded-pill">buttons <i class="mdi mdi-magnify ms-1"></i></a>
                        </div>
                        <!-- item-->
                        <div class="dropdown-header mt-2">
                            <h6 class="text-overflow text-muted mb-1 text-uppercase">Pages</h6>
                        </div>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item" style="display: none;">
                            <i class="ri-bubble-chart-line align-middle fs-18 text-muted me-2"></i>
                            <span>Analytics Dashboard</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item" style="display: none;">
                            <i class="ri-lifebuoy-line align-middle fs-18 text-muted me-2"></i>
                            <span>Help Center</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item" style="display: none;">
                            <i class="ri-user-settings-line align-middle fs-18 text-muted me-2"></i>
                            <span>My account settings</span>
                        </a>

                        <!-- item-->
                        <div class="dropdown-header mt-2">
                            <h6 class="text-overflow text-muted mb-2 text-uppercase">Members</h6>
                        </div>

                        
                    </div></div></div></div>
                    <div class="simplebar-placeholder" style="width: 0px; height: 0px;"></div></div>
                    <div class="simplebar-track simplebar-horizontal" style="visibility: hidden;"><div class="simplebar-scrollbar" style="width: 0px; display: none;"></div></div>
                    <div class="simplebar-track simplebar-vertical" style="visibility: hidden;"><div class="simplebar-scrollbar" style="height: 0px; display: none;"></div></div></div>

                    <div class="text-center pt-3 pb-1">
                        <a href="pages-search-results.html" class="btn btn-primary btn-sm">View All Results <i class="ri-arrow-right-line ms-1"></i></a>
                       
                    </div>
                </div>
            </form>
</div>
            <div class="d-flex align-items-center">
            <div class="dropdown d-md-none topbar-head-dropdown header-item">
                <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-search fs-22"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-search-dropdown">
                    <form class="p-3">
                        <div class="form-group m-0">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

                <div class="dropdown ms-1 topbar-head-dropdown header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <img id="header-lang-img" src="assets/images/flags/us.svg" alt="Header Language" height="20" class="rounded">
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language py-2" data-lang="en" title="English">
                            <img src="assets/images/flags/us.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">English</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="sp" title="Spanish">
                            <img src="assets/images/flags/spain.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">Española</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="gr" title="German">
                            <img src="assets/images/flags/germany.svg" alt="user-image" class="me-2 rounded" height="18"> <span class="align-middle">Deutsche</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="it" title="Italian">
                            <img src="assets/images/flags/italy.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">Italiana</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="ru" title="Russian">
                            <img src="assets/images/flags/russia.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">русский</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="ch" title="Chinese">
                            <img src="assets/images/flags/china.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">中国人</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="fr" title="French">
                            <img src="assets/images/flags/french.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">français</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item language" data-lang="ar" title="Arabic">
                            <img src="assets/images/flags/ae.svg" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">Arabic</span>
                        </a>
                    </div>
                </div>

                
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-toggle="fullscreen">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode">
                        <i class='bx bx-moon fs-22'></i>
                    </button>
                </div>

            
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user" src="<?php echo htmlspecialchars($user['avatar']);?>" alt="Header Avatar">
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-semibold user-name-text"><?php echo $userName;?></span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">Founder</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <h6 class="dropdown-header">Welcome <?php echo $userName;?> !</h6>
                        <a class="dropdown-item" href="profile.php"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Profile</span></a>
                        
                        <a class="dropdown-item" href="pages-faqs.html"><i class="mdi mdi-lifebuoy text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Help</span></a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="login.php"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle" data-key="t-logout">Logout</span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </header>
    

    <div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
                <!-- Dark Logo-->
                <a href="welcome.php" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="assets/images/logo-sm.png" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="assets/images/logo-dark.png" alt="" height="17">
                    </span>
                </a>
                <!-- Light Logo-->
                <a href="welcome.php" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="assets/images/logo-sm.png" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="assets/images/logo-light.png" alt="" height="17">
                    </span>
                </a>
                <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
                    <i class="ri-record-circle-line"></i>
                </button>
            </div>

            <div id="scrollbar">
                <div class="container-fluid">

                    <div id="two-column-menu"></div>
                    <ul class="navbar-nav" id="navbar-nav">
                        <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#" onclick="showContent('onaylanan')" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarDashboards">
                                <i class="ri-checkbox-line"></i> <span data-key="t-dashboards">Onaylanan İsteklerim</span>
                            </a>
                        </li> 

                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#" onclick="showContent('onayBekleyen')" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarIcons">
                                <i class="ri-time-line"></i> <span data-key="t-icons">Onay Bekleyen İsteklerim</span>
                            </a>
                            
                        </li>

                       
                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#" onclick="showContent('onayGereken')" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarMultilevel">
                                <i class="ri-share-line"></i> <span data-key="t-multi-level">Onaylamam Gereken İstekler</span>
                            </a>
                            
                        </li>

                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#" onclick="showContent('yeniIstek')" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarMultilevel">
                                <i class="ri-file-add-line"></i> <span data-key="t-multi-level">Yeni İstek</span>
                            </a>
                            
                        </li>

                        <li class="nav-item">
                            <a class="nav-link menu-link" href="/profile.php" role="button" aria-expanded="false" aria-controls="sidebarMultilevel">
                                <i class="ri-account-box-line"></i> <span data-key="t-multi-level">Profilim</span>
                            </a>
                            
                        </li>

                        <li class="nav-item">
                            <a class="nav-link menu-link" href="login.php" role="button" aria-expanded="false" aria-controls="sidebarMultilevel">
                                <i class="ri-logout-box-line"></i> <span data-key="t-multi-level">Çıkış Yap</span>
                            </a>
                            
                        </li>

                    </ul>
                </div>
                <!-- Sidebar -->
            </div>

            <div class="sidebar-background"></div>
        </div>
        <!-- Left Sidebar End -->
        <!-- Vertical Overlay-->
        <div class="vertical-overlay"></div>

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">İsteklerim</h4>
                            <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="javascript: void(0);">Kontrol</a></li>
                                        <li class="breadcrumb-item active">İsteklerim</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end page title -->

                    <div class="row">
                        <div class="col">
                            <div class="h-100">   
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <h4 class="card-title flex-grow-1">İstekleri gör</h4>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-soft-info btn-sm">
                                        <i class="ri-file-list-3-line align-middle"></i> Generate Report
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="onaylanan" class="table-responsive table-card">
                                    <h5 class="card-title">Onaylanan İsteklerim</h5>
                                    <table class="table table-borderless table-centered align-middle table-nowrap mb-0">
                                        <thead class="text-muted table-light">
                                            <tr>
                                                <th scope="col">ID</th>
                                                <th scope="col">Request</th>
                                                <th scope="col">Comment</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($approvedRequests as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['requestID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Request']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Comment']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['tarih']); ?></td>
                                                    <td><span class="badge bg-success-subtle text-success" >Onaylandı</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div id="onayBekleyen" class="table-responsive table-card">
                                    <h5 class="card-title">Onay Bekleyen İsteklerim</h5>
                                    <table class="table table-borderless table-centered align-middle table-nowrap mb-0">
                                        <thead class="text-muted table-light">
                                            <tr>
                                                <th scope="col">ID</th>
                                                <th scope="col">Request</th>
                                                <th scope="col">Comment</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($waitingRequests as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['requestID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Request']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Comment']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['tarih']); ?></td>
                                                    <td><span class="badge bg-warning-subtle text warning">Onay Bekliyor</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div id="onayGereken" class="table-responsive table-card">
                                    <h5 class="card-title">Onaylamam Gereken İstekler</h5>
                                    <table class="table table-borderless table-centered align-middle table-nowrap mb-0">
                                        <thead class="text-muted table-light">
                                            <tr>
                                                <th scope="col">ID</th>
                                                <th scope="col">Request</th>
                                                <th scope="col">Comment</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Actions</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingRequests as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['requestID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Request']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Comment']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['tarih']); ?></td>
                                                    <td>
                                                        <form method="POST" action="welcome.php">
                                                            <input type="hidden" name="approve_request_id" value="<?php echo $row['requestID']; ?>">
                                                            <input type="submit" value="Onayla" class="btn btn-primary btn-sm">
                                                        </form>
                                                    </td>
                                                    <td><span class="badge bg-warning"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div id="yeniIstek" class="table-responsive table-card">
                                    <h5 class="card-title">Yeni İstek</h5>
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="request" class="form-label">Request</label>
                                            <input type="text" class="form-control" id="request" name="request" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Comment</label>
                                            <textarea class="form-control" id="comment" name="comment" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                        </form>
                                            </div>
                                        </div>
                     
                                        </div> <!-- .card-->
                                    </div> <!-- .col-->
                                </div> <!-- end row-->

                            </div> <!-- end .h-100-->

                        </div> <!-- end col -->

                        <div class="col-auto layout-rightside-col">
                            <div class="overlay"></div>
                            <div class="layout-rightside">
                                <div class="card h-100 rounded-0 card-border-effect-none">
                                   
                                </div> <!-- end card-->
                            </div> <!-- end .rightbar-->

                        </div> <!-- end col -->
                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <script>document.write(new Date().getFullYear())</script> © ZCE.
                </div>
                <div class="col-sm-6">
                    <div class="text-sm-end">
                        Design & Develop by Ceren Engin
                    </div>
                </div>
            </div>
        </div>
    </footer>
    </div>
        <!-- end main content-->

    </div>
    <!-- END layout-wrapper -->

    <!--start back-to-top-->
    <button onclick="topFunction()" class="btn btn-primary btn-icon" id="back-to-top">
        <i class="ri-arrow-up-line"></i>
    </button>
    <!--end back-to-top-->

    <!-- JAVASCRIPT -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/node-waves/waves.min.js"></script>
    <script src="assets/libs/feather-icons/feather.min.js"></script>
    <script src="assets/js/pages/plugins/lord-icon-2.1.0.js"></script>
    <script src="assets/js/plugins.js"></script>

    <!-- apexcharts -->
    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>

    <!-- Vector map-->
    <script src="assets/libs/jsvectormap/js/jsvectormap.min.js"></script>
    <script src="assets/libs/jsvectormap/maps/world-merc.js"></script>

    <!--Swiper slider js-->
    <script src="assets/libs/swiper/swiper-bundle.min.js"></script>

    <!-- Dashboard init -->
    <script src="assets/js/pages/dashboard-ecommerce.init.js"></script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.table-card');
            sections.forEach(section => section.style.display = 'none');
            document.getElementById('onaylanan').style.display = 'block';
        });

        function showContent(sectionId) {
            const sections = document.querySelectorAll('.table-card');
            sections.forEach(section => section.style.display = 'none');
            document.getElementById(sectionId).style.display = 'block';
        }
    </script>
</body>
</html>



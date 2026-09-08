<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.html');
    exit;
}
$userRole = strtolower(trim((string) ($_SESSION['user']['quyen'] ?? '')));
$homeUrl = $userRole === 'admin' ? 'admin.html' : 'customer.php';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cài đặt bảo mật - Quản lý tiền điện</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <style>
    .security-page { max-width: 920px; margin: 42px auto 64px; }
    .security-header { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:28px; }
    .security-kicker { color:#c2410c; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; margin:0 0 8px; }
    .security-header h1 { margin:0 0 8px; color:#102a43; font-size:clamp(28px, 4vw, 40px); }
    .security-header p { margin:0; color:#526579; }
    .security-card { background:#fff; border:1px solid #dbe5ef; border-radius:18px; padding:30px; box-shadow:0 20px 48px rgba(16,42,67,.1); }
    .security-account { display:flex; align-items:center; gap:16px; padding:16px 18px; margin-bottom:26px; background:#f3f8fc; border:1px solid #dceaf4; border-radius:12px; }
    .security-account-mark { display:grid; place-items:center; width:44px; height:44px; flex:0 0 44px; border-radius:50%; background:#0f766e; color:#fff; font-size:20px; font-weight:800; }
    .security-account strong, .security-account span { display:block; }
    .security-account strong { color:#102a43; }
    .security-account span { margin-top:3px; color:#60758a; font-size:13px; }
    .security-options { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; }
    .security-option { display:flex; align-items:center; gap:14px; min-height:92px; border:1px solid #d9e4ee; border-radius:12px; padding:17px; background:#fff; color:#102a43; text-align:left; cursor:pointer; transition:.2s ease; }
    .security-option:hover, .security-option:focus-visible { border-color:#0f766e; box-shadow:0 8px 18px rgba(15,118,110,.12); outline:none; transform:translateY(-1px); }
    .security-option.active { background:#effaf8; border-color:#0f766e; box-shadow:inset 4px 0 #0f766e; }
    .security-option-icon { display:grid; place-items:center; width:42px; height:42px; flex:0 0 42px; border-radius:10px; background:#e7f5f3; color:#0f766e; font-size:21px; font-weight:800; }
    .security-option strong, .security-option small { display:block; }
    .security-option small { margin-top:5px; color:#65788b; font-size:12px; }
    .security-flow { display:none; margin-top:28px; padding-top:28px; border-top:1px solid #e3ebf2; animation:security-reveal .25s ease-out; }
    .security-flow.active { display:block; }
    .security-flow h2 { margin:0 0 8px; color:#102a43; font-size:23px; }
    .security-flow > p { margin:0 0 22px; color:#60758a; font-size:14px; }
    .security-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; max-width:680px; }
    .security-field { display:flex; flex-direction:column; gap:7px; }
    .security-field.full { grid-column:1 / -1; }
    .security-field label { color:#27445e; font-size:13px; font-weight:700; }
    .security-field input { width:100%; padding:12px 14px; border:1px solid #c9d8e5; border-radius:8px; color:#102a43; background:#fff; transition:.2s; }
    .security-field input:focus { border-color:#0f766e; box-shadow:0 0 0 3px rgba(15,118,110,.12); outline:none; }
    .security-password-wrap { position:relative; }
    .security-password-wrap input { padding-right:42px; }
    .security-eye { position:absolute; right:5px; top:4px; width:34px; height:34px; border:0; background:transparent; color:#60758a; cursor:pointer; font-size:16px; }
    .security-strength { height:5px; margin-top:8px; border-radius:99px; background:#e5edf3; overflow:hidden; }
    .security-strength span { display:block; width:0; height:100%; background:#dc2626; transition:.2s; }
    .security-hint { margin:6px 0 0; color:#718398; font-size:12px; }
    .security-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:22px; }
    .security-actions .btn { border-radius:8px; padding:10px 16px; font-size:13px; }
    .security-status { min-height:22px; margin:14px 0 0; color:#60758a; font-size:13px; font-weight:700; }
    .security-status.ok { color:#087f5b; }
    .security-status.error { color:#c92a2a; }
    .face-status-line { display:flex; align-items:center; gap:9px; margin:0 0 18px; color:#60758a; font-size:13px; }
    .face-status-dot { width:9px; height:9px; border-radius:50%; background:#94a3b8; }
    .face-status-dot.active { background:#0f766e; box-shadow:0 0 0 4px #d8f3ef; }
    .face-history { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; padding:14px 16px; border:1px solid #dceaf4; border-radius:10px; background:#f3f8fc; }
    .face-history[hidden], .face-scan-form[hidden] { display:none; }
    .face-history-label { margin:0 0 4px; color:#27445e; font-size:12px; font-weight:700; }
    .face-history-value { margin:0; color:#60758a; font-size:13px; }
    .face-history .security-actions { margin-top:0; }
    .security-camera { width:100%; max-width:560px; aspect-ratio:16/10; object-fit:cover; background:#101c30; border-radius:12px; display:block; margin:18px 0 0; }
    @keyframes security-reveal { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:none; } }
    @media (max-width:640px) { .security-page { margin-top:28px; } .security-card { padding:20px; } .security-header { align-items:flex-start; flex-direction:column; } .security-options, .security-form-grid { grid-template-columns:1fr; } .security-field.full { grid-column:auto; } .security-option { min-height:78px; } }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-top">
      <div class="container header-top-inner">
        <a class="brand-left" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">
          <img src="assets/img/Logo-EVN-V-1.webp" alt="EVN">
          <div class="brand-text"><div class="brand-title">TẬP ĐOÀN ĐIỆN LỰC VIỆT NAM</div><div class="brand-sub">CÀI ĐẶT TÀI KHOẢN</div></div>
        </a>
        <a class="btn btn-secondary" href="logout.php">Đăng xuất</a>
      </div>
    </div>
  </header>

  <main class="container security-page">
    <div class="security-header">
      <div><p class="security-kicker">Trung tâm tài khoản</p><h1>Cài đặt bảo mật</h1><p>Kiểm soát cách tài khoản được bảo vệ và truy cập.</p></div>
      <a class="btn btn-secondary" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Quay lại</a>
    </div>
    <section class="security-card">
      <div class="security-account"><span class="security-account-mark">✓</span><div><strong>Tài khoản đang được bảo vệ</strong><span><?= htmlspecialchars($_SESSION['user']['hovaten'] ?? $_SESSION['user']['maKH'] ?? 'Người dùng', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($_SESSION['user']['maKH'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div></div>
      <div class="security-options">
        <button class="security-option active" type="button" data-flow="passwordFlow" aria-selected="true"><span class="security-option-icon">K</span><span><strong>Đổi mật khẩu</strong><small>Cập nhật thông tin đăng nhập định kỳ</small></span></button>
        <button class="security-option" type="button" data-flow="faceFlow" aria-selected="false"><span class="security-option-icon">F</span><span><strong>Đăng nhập bằng khuôn mặt</strong><small>Thiết lập xác thực nhanh trên thiết bị này</small></span></button>
      </div>

      <div id="passwordFlow" class="security-flow active">
        <h2>Đổi mật khẩu</h2>
        <p>Dùng mật khẩu mạnh và không trùng với các tài khoản khác của bạn.</p>
        <form id="customerPasswordForm">
        <div class="security-form-grid">
          <div class="security-field full"><label for="customerSecurityCurrentPassword">Mật khẩu hiện tại</label><div class="security-password-wrap"><input id="customerSecurityCurrentPassword" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu hiện tại"><button class="security-eye" type="button" data-target="customerSecurityCurrentPassword" aria-label="Hiện mật khẩu">◉</button></div></div>
          <div class="security-field"><label for="customerSecurityNewPassword">Mật khẩu mới</label><div class="security-password-wrap"><input id="customerSecurityNewPassword" type="password" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự"><button class="security-eye" type="button" data-target="customerSecurityNewPassword" aria-label="Hiện mật khẩu">◉</button></div><div class="security-strength"><span id="securityPasswordStrength"></span></div><p class="security-hint">Nên kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt.</p></div>
          <div class="security-field"><label for="customerSecurityConfirmPassword">Xác nhận mật khẩu mới</label><div class="security-password-wrap"><input id="customerSecurityConfirmPassword" type="password" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới"><button class="security-eye" type="button" data-target="customerSecurityConfirmPassword" aria-label="Hiện mật khẩu">◉</button></div></div>
        </div>
        <div id="passwordChangeStatus" class="security-status" role="status" aria-live="polite"></div>
        <div class="security-actions"><button id="customerChangePassword" class="btn" type="submit">Cập nhật mật khẩu</button></div>
        </form>
      </div>

      <div id="faceFlow" class="security-flow">
        <h2>Đăng ký khuôn mặt</h2>
        <p>Mẫu khuôn mặt được mã hóa trước khi lưu. Bạn có thể xóa đăng ký bất cứ lúc nào.</p>
        <div id="faceHistory" class="face-history" hidden>
          <div><p class="face-history-label">Lịch sử đăng ký gần nhất</p><p id="faceRegistrationStatus" class="face-history-value"></p></div>
          <div class="security-actions"><button id="customerFaceRemove" class="btn btn-secondary" type="button">Xóa đăng ký</button></div>
        </div>
        <div class="face-status-line"><span id="faceStatusDot" class="face-status-dot"></span><span id="faceStatusText">Chưa đăng ký khuôn mặt</span></div>
        <form id="customerFacePasswordForm">
          <div class="security-form-grid"><div class="security-field full"><label for="customerFaceSecurityCurrentPassword">Xác nhận bằng mật khẩu hiện tại</label><div class="security-password-wrap"><input id="customerFaceSecurityCurrentPassword" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu hiện tại"><button class="security-eye" type="button" data-target="customerFaceSecurityCurrentPassword" aria-label="Hiện mật khẩu">◉</button></div></div></div>
          <div id="customerFacePasswordStatus" class="security-status" role="status" aria-live="polite"></div>
          <div class="security-actions"><button id="customerFaceStart" class="btn" type="submit">Xác nhận mật khẩu</button></div>
        </form>
        <form id="customerFaceScanForm" class="face-scan-form" hidden>
          <video id="customerFaceCamera" class="security-camera" autoplay muted playsinline></video>
          <div id="customerSecurityStatus" class="security-status" role="status" aria-live="polite"></div>
        </form>
      </div>
    </section>
  </main>
  <script>
    document.querySelectorAll('.security-option').forEach(function (option) {
      option.addEventListener('click', function () {
        document.querySelectorAll('.security-option').forEach(function (item) { item.classList.remove('active'); item.setAttribute('aria-selected', 'false'); });
        document.querySelectorAll('.security-flow').forEach(function (flow) { flow.classList.remove('active'); });
        option.classList.add('active');
        option.setAttribute('aria-selected', 'true');
        document.getElementById(option.dataset.flow).classList.add('active');
      });
    });
  </script>
  <script src="assets/js/security-settings.js"></script>
</body>
</html>

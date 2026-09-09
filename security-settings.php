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
    .security-page { max-width:1000px; margin:42px auto 72px; }
    .security-header { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:26px; }
    .security-kicker { display:inline-flex; align-items:center; gap:7px; color:#c2410c; font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; margin:0 0 10px; }
    .security-kicker::before { content:""; width:26px; height:2px; background:#f97316; }
    .security-header h1 { margin:0 0 9px; color:#102a43; font-size:clamp(30px, 4vw, 44px); letter-spacing:0; }
    .security-header p { margin:0; color:#526579; font-size:15px; }
    .security-header .btn { border-radius:10px; white-space:nowrap; }
    .security-card { position:relative; overflow:hidden; background:#fff; border:1px solid #dbe5ef; border-radius:22px; padding:30px; box-shadow:0 24px 58px rgba(16,42,67,.12); }
    .security-card::before { content:""; position:absolute; inset:0 0 auto; height:5px; background:linear-gradient(90deg,#0f766e 0 58%,#f97316 58% 100%); }
    .security-account { display:flex; align-items:center; gap:16px; padding:17px 18px; margin-bottom:26px; background:linear-gradient(105deg,#f0faf8,#f7fbfd); border:1px solid #d6ebe7; border-radius:15px; }
    .security-account-mark { display:grid; place-items:center; width:48px; height:48px; flex:0 0 48px; border-radius:14px; background:#0f766e; color:#fff; font-size:21px; font-weight:800; box-shadow:0 8px 18px rgba(15,118,110,.2); }
    .security-account strong, .security-account span { display:block; }
    .security-account strong { color:#102a43; }
    .security-account span { margin-top:3px; color:#60758a; font-size:13px; }
    .security-options { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
    .security-option { position:relative; display:flex; align-items:center; gap:15px; min-height:104px; border:1px solid #d9e4ee; border-radius:15px; padding:19px; background:#fff; color:#102a43; text-align:left; cursor:pointer; transition:.2s ease; }
    .security-option::after { content:""; position:absolute; right:18px; top:18px; width:8px; height:8px; border:1px solid #b7c7d5; border-radius:50%; }
    .security-option:hover, .security-option:focus-visible { border-color:#0f766e; box-shadow:0 12px 24px rgba(15,118,110,.12); outline:none; transform:translateY(-2px); }
    .security-option.active { background:#effaf8; border-color:#0f766e; box-shadow:inset 4px 0 #0f766e, 0 12px 24px rgba(15,118,110,.1); }
    .security-option.active::after { background:#0f766e; border-color:#0f766e; box-shadow:0 0 0 4px #d7f2ed; }
    .security-option-icon { display:grid; place-items:center; width:46px; height:46px; flex:0 0 46px; border-radius:13px; background:#e7f5f3; color:#0f766e; font-size:21px; font-weight:800; }
    .security-option strong, .security-option small { display:block; }
    .security-option small { margin-top:5px; color:#65788b; font-size:12px; }
    .security-flow { display:none; margin-top:30px; padding:28px 4px 2px; border-top:1px solid #e3ebf2; animation:security-reveal .25s ease-out; }
    .security-flow.active { display:block; }
    .security-flow h2 { margin:0 0 8px; color:#102a43; font-size:24px; letter-spacing:0; }
    .security-flow > p { margin:0 0 22px; color:#60758a; font-size:14px; }
    .security-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; max-width:680px; }
    .security-field { display:flex; flex-direction:column; gap:7px; }
    .security-field.full { grid-column:1 / -1; }
    .security-field label { color:#27445e; font-size:13px; font-weight:700; }
    .security-field input { width:100%; padding:13px 14px; border:1px solid #c9d8e5; border-radius:10px; color:#102a43; background:#fbfdff; transition:.2s; }
    .security-field input:focus { border-color:#0f766e; box-shadow:0 0 0 3px rgba(15,118,110,.12); outline:none; }
    .security-password-wrap { position:relative; }
    .security-password-wrap input { padding-right:42px; }
    .security-eye { position:absolute; right:5px; top:4px; width:34px; height:34px; border:0; background:transparent; color:#60758a; cursor:pointer; font-size:16px; }
    .security-strength { height:5px; margin-top:8px; border-radius:99px; background:#e5edf3; overflow:hidden; }
    .security-strength span { display:block; width:0; height:100%; background:#dc2626; transition:.2s; }
    .security-hint { margin:6px 0 0; color:#718398; font-size:12px; }
    .security-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:22px; }
    .security-actions .btn { border-radius:10px; padding:11px 17px; font-size:13px; }
    .security-status { min-height:22px; margin:14px 0 0; color:#60758a; font-size:13px; font-weight:700; }
    .security-status.ok { color:#087f5b; }
    .security-status.error { color:#c92a2a; }
    .face-status-line { display:flex; align-items:center; gap:9px; margin:0 0 18px; color:#60758a; font-size:13px; }
    .face-status-dot { width:9px; height:9px; border-radius:50%; background:#94a3b8; }
    .face-status-dot.active { background:#0f766e; box-shadow:0 0 0 4px #d8f3ef; }
    .face-history { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; padding:15px 17px; border:1px solid #dceaf4; border-radius:12px; background:#f3f8fc; }
    .face-history[hidden], .face-scan-form[hidden] { display:none; }
    .face-history-label { margin:0 0 4px; color:#27445e; font-size:12px; font-weight:700; }
    .face-history-value { margin:0; color:#60758a; font-size:13px; }
    .face-history .security-actions { margin-top:0; }
    .security-camera { width:100%; max-width:560px; aspect-ratio:16/10; object-fit:cover; background:#101c30; border:5px solid #e8f1f4; border-radius:16px; display:block; margin:18px 0 0; box-shadow:0 16px 30px rgba(16,42,67,.14); }
    @keyframes security-reveal { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:none; } }
    @media (max-width:640px) { .security-page { margin-top:28px; } .security-card { padding:24px 18px; border-radius:18px; } .security-header { align-items:flex-start; flex-direction:column; } .security-header .btn { width:100%; } .security-options, .security-form-grid { grid-template-columns:1fr; } .security-field.full { grid-column:auto; } .security-option { min-height:82px; padding:16px; } .security-flow { padding-left:0; padding-right:0; } }
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

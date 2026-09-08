<?php
session_start();

require 'config.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['quyen'] ?? '', ['khachhang', 'nguoidung'], true)) {
    header('Location: login.html');
    exit;
}

$maKH = $_SESSION['user']['maKH'];

// Lấy hóa đơn gần nhất của khách hàng
$stmt = $conn->prepare(
    "SELECT h.maHD, h.tongtien, h.trangthai, h.hansudung, c.chisocu, c.chisomoi, c.thang, c.nam
     FROM hoadon h
     JOIN chisodien c ON h.maCSD = c.maCSD
     WHERE h.maKH = ?
     ORDER BY c.nam DESC, c.thang DESC
     LIMIT 1"
);
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

$soKWh = null;
$statusText = 'Không có hóa đơn';
$amountText = '-';
$periodText = '-';

if ($invoice) {
    $soKWh = intval($invoice['chisomoi']) - intval($invoice['chisocu']);
    $amountText = number_format(floatval($invoice['tongtien']), 0, ',', '.');
    $periodText = intval($invoice['thang']) . '/' . intval($invoice['nam']);
    $statusText = ($invoice['trangthai'] === 'dathanhtoan') ? 'Đã thanh toán' : 'Chưa thanh toán';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Khách hàng - Quản lý tiền điện</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <style>
    .customer-chat { margin-top: 32px; }
    .chat-card { background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:24px; box-shadow:0 18px 36px rgba(15,23,42,.08); }
    .chat-card h3 { margin-top:0; color:#1f365f; }
    .chat-card p { margin-bottom:18px; color:#475569; }
    .chat-window { min-height:220px; max-height:320px; overflow-y:auto; border:1px solid #e6edf8; border-radius:18px; padding:16px; background:#f8fbff; }
    .chat-message { margin-bottom:14px; display:flex; }
    .chat-message.user { justify-content:flex-end; }
    .chat-message .bubble { max-width:80%; padding:12px 16px; border-radius:18px; line-height:1.5; }
    .chat-message.user .bubble { background:#1c4ab2; color:#fff; border-bottom-right-radius:4px; }
    .chat-message.bot .bubble { background:#eef4ff; color:#1a2b53; border-bottom-left-radius:4px; }
    .chat-input-row { display:flex; gap:10px; margin-top:16px; }
    .chat-input-row input { flex:1; padding:14px 16px; border-radius:18px; border:1px solid #cbd5e1; background:#fff; font-size:15px; outline:none; }
    .chat-input-row button { min-width:120px; }
    .floating-chat-button {
      position:fixed;
      right:24px;
      bottom:24px;
      width:62px;
      height:62px;
      border-radius:50%;
      background:#1c4ab2;
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      box-shadow:0 18px 36px rgba(28,74,178,.18);
      cursor:pointer;
      z-index:9999;
      border:none;
      font-size:26px;
    }
    .floating-chat-panel {
      position:fixed;
      right:24px;
      bottom:100px;
      width:360px;
      max-height:520px;
      background:#fff;
      border:1px solid #dbe4f1;
      border-radius:24px;
      box-shadow:0 24px 48px rgba(15,23,42,.16);
      z-index:9999;
      display:none;
      flex-direction:column;
      overflow:hidden;
    }
    .floating-chat-panel.open { display:flex; }
    .floating-chat-panel .chat-panel-header {
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:18px 18px 0 18px;
      border-bottom:1px solid #eef3fb;
      background:#f8fbff;
    }
    .floating-chat-panel .chat-panel-header h3 { margin:0; font-size:16px; color:#1f365f; }
    .floating-chat-panel .chat-panel-header button { background:none; border:none; color:#1c4ab2; font-size:18px; cursor:pointer; }
    .floating-chat-panel .chat-body { padding:16px; display:flex; flex-direction:column; gap:12px; }
    .floating-chat-panel .chat-window { min-height:220px; max-height:320px; overflow-y:auto; border:1px solid #e6edf8; border-radius:18px; padding:16px; background:#f8fbff; }
    .floating-chat-panel .chat-input-row { margin-top:12px; }
    .customer-security { margin-top:28px; padding:24px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; }
    .customer-security video { width:100%; max-width:520px; aspect-ratio:4/3; object-fit:cover; background:#0f172a; border-radius:12px; display:block; margin:12px 0; }
  </style>
</head>
<body class="customer-page-shell">
  <header class="site-header">
    <div class="header-top">
      <div class="container header-top-inner">
        <div class="brand">
          <a class="brand-left" href="customer.php">
            <img src="assets/img/Logo-EVN-V-1.webp" alt="MOON" />
            <div class="brand-text">
              <div class="brand-title">TẬP ĐOÀN ĐIỆN LỰC VIỆT NAM</div>
              <div class="brand-sub">TRANG THÔNG TIN ĐIỆN TỬ TỔNG HỢP</div>
            </div>
          </a>
        </div>
        <div class="header-top-right">
          <div class="search">
            <input type="search" placeholder="Tìm kiếm" aria-label="Tìm kiếm">
          </div>
          <div style="margin-left:12px;">
            <a class="btn btn-secondary" href="logout.php">Đăng xuất</a>
          </div>
        </div>
      </div>
    </div>
      <div class="container customer-nav">
      <a href="invoice-search.html" class="customer-nav-item active">Tra cứu hóa đơn</a>
      <a href="customer-usage-chart.php" class="customer-nav-item">Biểu đồ sử dụng điện</a>
      <a href="payment-history.html" class="customer-nav-item">Lịch sử thanh toán</a>
      <a href="security-settings.php" class="customer-nav-item">&#9881; Cài đặt</a>
    </div>
  </header>

  <main class="container customer-page">
    <section class="page-intro">
      <h1>Trang chủ </h1>
      <p>Chào mừng <?= htmlspecialchars($_SESSION['user']['hovaten']) ?> đến với hệ thống quản lý tiền điện. Chọn chức năng cần thiết để tra cứu, thanh toán và xem lịch sử đơn giản.</p>
    </section>

    <section class="action-cards">
      <article class="card">
        <h3>Tra cứu hóa đơn</h3>
        <p>Xem chi tiết các kỳ hóa đơn, số điện, và số tiền phải thanh toán.</p>
        <a class="btn" href="invoice-search.html">Mở tra cứu</a>
      </article>
      <article class="card">
        <h3>Biểu đồ sử dụng điện</h3>
        <p>Theo dõi mức tiêu thụ và tiền điện theo từng tháng trong năm.</p>
        <a class="btn" href="customer-usage-chart.php">Xem biểu đồ</a>
      </article>
      <article class="card">
        <h3>Lịch sử thanh toán</h3>
        <p>Xem lại các giao dịch đã thực hiện và biên lai điện tử.</p>
        <a class="btn" href="payment-history.html">Xem lịch sử</a>
      </article>
    </section>

    <section class="customer-summary">
      <div class="summary-block">
        <h4>Hóa đơn gần nhất</h4>
        <p><?= $periodText ?></p>
        <strong><?= $amountText ?> ₫</strong>
      </div>
      <div class="summary-block">
        <h4>Số điện tiêu thụ</h4>
        <p><?= $soKWh !== null ? $soKWh . ' kWh' : '-' ?></p>
      </div>
      <div class="summary-block">
        <h4>Trạng thái</h4>
        <p><?= htmlspecialchars($statusText) ?></p>
      </div>
    </section>

  </main>

  <button id="floatingChatLauncher" class="floating-chat-button" title="Trợ lý tiền điện">💬</button>
  <div id="customerChatPanel" class="floating-chat-panel" aria-label="Chatbot trợ lý khách hàng">
    <div class="chat-panel-header">
      <h3>Trợ lý tiền điện</h3>
      <button id="closeChatPanel" aria-label="Đóng">×</button>
    </div>
    <div class="chat-body">
      <p style="margin:0; font-size:14px; color:#475569;">Bạn có thể hỏi về hóa đơn, tiêu thụ, thanh toán, giá điện, blockchain không khớp hoặc lịch sử thanh toán.</p>
      <div id="customerChatWindow" class="chat-window"></div>
      <div class="chat-input-row">
        <input id="customerChatInput" type="text" placeholder="Nhập câu hỏi của bạn..." autocomplete="off" />
        <button id="customerChatSend" class="btn btn-secondary">Gửi</button>
      </div>
    </div>
  </div>

  <footer class="site-footer footer-simple">
    <div class="container">
      <p>© 2026 Trọng Thành - Trang quản lý tiền điện.</p>
    </div>
  </footer>
  <script>
    const customerChatWindow = document.getElementById('customerChatWindow');
    const customerChatInput = document.getElementById('customerChatInput');
    const customerChatSend = document.getElementById('customerChatSend');
    const floatingChatLauncher = document.getElementById('floatingChatLauncher');
    const customerChatPanel = document.getElementById('customerChatPanel');
    const closeChatPanel = document.getElementById('closeChatPanel');
    const latestInvoice = {
      period: <?= json_encode($periodText) ?>,
      amount: <?= json_encode(floatval($invoice['tongtien'] ?? 0)) ?>,
      soKWh: <?= json_encode($soKWh !== null ? $soKWh : 0) ?>,
      status: <?= json_encode($statusText) ?>
    };

    function appendCustomerMessage(text, type = 'bot') {
      const messageEl = document.createElement('div');
      messageEl.className = `chat-message ${type === 'user' ? 'user' : 'bot'}`;
      const bubble = document.createElement('div');
      bubble.className = 'bubble';
      bubble.textContent = text;
      messageEl.appendChild(bubble);
      customerChatWindow.appendChild(messageEl);
      customerChatWindow.scrollTop = customerChatWindow.scrollHeight;
    }

    function smartChatReply(text) {
      const lower = text.toLowerCase();
      if (!lower) return 'Xin hãy nhập câu hỏi để tôi hỗ trợ bạn.';

      const kwh = latestInvoice.soKWh;
      const amount = latestInvoice.amount;
      const predicted = kwh > 0 ? Math.round(kwh * 2500 * 1.05) : 0;
      const hasInvoice = kwh > 0 && amount > 0;

      const blockchainKeywords = ['blockchain', 'chuỗi khối', 'không khớp', 'mismatch', 'xác minh', 'tamper', 'lỗi blockchain'];
      const paymentKeywords = ['thanh toán', 'thanhtoan', 'qr', 'mã qr', 'quét', 'chưa thanh toán', 'đã thanh toán'];
      const invoiceKeywords = ['hóa đơn', 'hđ', 'tiền', 'số tiền', 'tổng tiền'];
      const consumeKeywords = ['tiêu thụ', 'kwh', 'số điện', 'mức tiêu thụ'];
      const predictKeywords = ['dự đoán', 'dự đoán tiền', 'tiền tháng', 'ước lượng'];
      const statusKeywords = ['trạng thái', 'tình trạng', 'tình hình'];
      const priceKeywords = ['giá điện', 'giá mới', 'bậc', 'đơn giá'];
      const historyKeywords = ['lịch sử', 'quá khứ', 'giao dịch'];

      if (blockchainKeywords.some(word => lower.includes(word))) {
        return 'Nếu blockchain không khớp, điều đó có nghĩa là dữ liệu lịch sử chỉ số hoặc hóa đơn đã được đối chiếu và phát hiện sự khác biệt. Hệ thống sẽ ghi lại sự cố này để kiểm toán. Bạn nên liên hệ bộ phận kỹ thuật hoặc quản trị để xác minh chuỗi khối và đối chiếu lại bản ghi.';
      }
      if (invoiceKeywords.some(word => lower.includes(word))) {
        return hasInvoice
          ? `Hóa đơn gần nhất của bạn (${latestInvoice.period}) là ${amount.toLocaleString('vi-VN')} ₫ với ${kwh} kWh tiêu thụ. Trạng thái: ${latestInvoice.status}.`
          : 'Hiện tại bạn chưa có hóa đơn gần nhất để hiển thị.';
      }
      if (consumeKeywords.some(word => lower.includes(word))) {
        return hasInvoice
          ? `Mức tiêu thụ gần nhất là ${kwh} kWh trong kỳ ${latestInvoice.period}. Đây là dữ liệu dùng để tính toán hóa đơn và dự đoán.`
          : 'Chưa có dữ liệu tiêu thụ để trình bày.';
      }
      if (predictKeywords.some(word => lower.includes(word))) {
        return hasInvoice
          ? `Dựa trên mức tiêu thụ ${kwh} kWh và đơn giá tham khảo, dự đoán tiền điện tháng tới khoảng ${predicted.toLocaleString('vi-VN')} ₫. Đây là giá trị ước tính, thực tế có thể khác khi giá điện thay đổi.`
          : 'Chưa đủ dữ liệu để dự đoán tiền điện. Vui lòng kiểm tra hóa đơn gần nhất trước.';
      }
      if (paymentKeywords.some(word => lower.includes(word))) {
        return 'Bạn có thể thanh toán qua trang QR Code hoặc theo hướng dẫn trên trang thanh toán. Nếu hóa đơn chưa thanh toán, vui lòng thực hiện ngay để tránh phát sinh phí trễ hạn.';
      }
      if (statusKeywords.some(word => lower.includes(word))) {
        return `Trạng thái hiện tại của hóa đơn gần nhất là: ${latestInvoice.status}. Nếu bạn cần xem chi tiết, truy cập phần Tra cứu hóa đơn.`;
      }
      if (priceKeywords.some(word => lower.includes(word))) {
        return 'Giá điện được cập nhật theo bậc tiêu thụ và ngày áp dụng. Nếu bạn muốn biết mức giá mới nhất, có thể truy cập phần quản trị để xem chi tiết các bậc giá và ngày áp dụng.';
      }
      if (historyKeywords.some(word => lower.includes(word))) {
        return 'Lịch sử thanh toán hiển thị các giao dịch trước đây và trạng thái thanh toán. Bạn có thể kiểm tra lại để đối chiếu biên lai và thời gian trả tiền.';
      }
      if (lower.includes('xin chào') || lower.includes('hello') || lower.includes('hi')) {
        return 'Chào bạn! Tôi có thể hỗ trợ tra cứu hóa đơn, tiêu thụ, dự đoán tiền điện, thanh toán và kiểm tra trạng thái blockchain không khớp.';
      }
      return 'Tôi đã hiểu câu hỏi của bạn. Bạn có thể hỏi về hóa đơn, mức tiêu thụ, thanh toán, dự đoán tiền điện, lịch sử hoặc blockchain không khớp.';
    }

    function openChatPanel() {
      customerChatPanel.classList.add('open');
      customerChatInput.focus();
    }

    function closeChatPanelHandler() {
      customerChatPanel.classList.remove('open');
    }

    function sendCustomerChat() {
      const text = customerChatInput.value.trim();
      if (!text) return;
      appendCustomerMessage(text, 'user');
      customerChatInput.value = '';
      const reply = smartChatReply(text);
      setTimeout(() => appendCustomerMessage(reply, 'bot'), 250);
    }

    floatingChatLauncher.addEventListener('click', openChatPanel);
    closeChatPanel.addEventListener('click', closeChatPanelHandler);
    customerChatSend.addEventListener('click', sendCustomerChat);
    customerChatInput.addEventListener('keypress', function(event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        sendCustomerChat();
      }
    });

    document.addEventListener('DOMContentLoaded', function() {
      appendCustomerMessage('Xin chào! Tôi là trợ lý ảo của bạn. Bạn có thể hỏi về hóa đơn, mức tiêu thụ, thanh toán, dự đoán tiền điện hoặc blockchain không khớp.');
    });
  </script>
  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/security-settings.js"></script>
</body>
</html>

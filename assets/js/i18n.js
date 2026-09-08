(function () {
  'use strict';

  const translations = {
    'Quản Lý Tiền Điện - Trang chủ': 'Electricity Billing Management - Home',
    'Admin - Quản lý tiền điện': 'Admin - Electricity Billing Management',
    'Chọn ngôn ngữ': 'Choose language',
    'Biểu đồ': 'Chart',
    'Trang chủ': 'Home',
    'Tra cứu tiền điện': 'Bill lookup',
    'Tra cứu hóa đơn': 'Bill lookup',
    'Thanh toán hóa đơn': 'Bill payment',
    'Thanh toán': 'Payment',
    'Lịch sử thanh toán': 'Payment history',
    'Đăng nhập': 'Sign in',
    'Đăng xuất': 'Sign out',
    'Tìm kiếm': 'Search',
    'Cổng Dịch Vụ - Hỗ trợ:': 'Service portal - Support:',
    'Trang chủ ': 'Home ',
    'Biểu đồ sử dụng điện': 'Usage chart',
    'Hệ thống quản lý tiền điện': 'Electricity billing management system',
    'Tỷ trọng phụ tải điện - Hệ thống quản lý tiền điện': 'Electricity load profile - Billing management system',
    'Giao diện tham khảo từ EVN, được tối giản cho ứng dụng quản lý tiền điện của bạn.': 'An EVN-inspired interface, streamlined for your electricity billing needs.',
    'Tin mới': 'Latest news',
    'Chuyên đề': 'Featured topics',
    'Liên kết nhanh': 'Quick links',
    'Kinh doanh – Dịch vụ': 'Business - Services',
    'Thông tin - Sự kiện': 'News - Events',
    'Cung ứng điện mùa khô 2026': 'Dry-season power supply 2026',
    'Tiết kiệm điện và điện mặt trời mái nhà': 'Energy saving and rooftop solar',
    'Cổng dịch vụ công': 'Public service portal',
    'Các dự án': 'Projects',
    'Đăng nhập': 'Sign in',
    'Tài khoản': 'Account',
    'Số điện thoại': 'Phone number',
    'Mã khách hàng': 'Customer ID',
    'Mật khẩu': 'Password',
    'Nhập mã khách hàng': 'Enter customer ID',
    'Nhập mật khẩu': 'Enter password',
    'Nhập số điện thoại': 'Enter phone number',
    'Quên tên đăng nhập': 'Forgot customer ID',
    'Quên mật khẩu ': 'Forgot password ',
    'Quý khách hàng chưa có tài khoản.': 'Do not have an account?',
    'Đăng ký ngay': 'Register now',
    'Hướng dẫn': 'Guide',
    'Mở tra cứu': 'Open lookup',
    'Xem chi tiết các kỳ hóa đơn, số điện, và số tiền phải thanh toán.': 'View billing periods, meter usage, and the amount due.',
    'Thanh toán trực tuyến an toàn bằng nhiều phương thức.': 'Pay online securely with multiple payment methods.',
    'Xem lại các giao dịch đã thực hiện và biên lai điện tử.': 'Review completed transactions and electronic receipts.',
    'Hóa đơn gần nhất': 'Latest bill',
    'Số điện tiêu thụ': 'Energy consumed',
    'Trạng thái': 'Status',
    'Đã phát hành': 'Issued',
    'Xem lịch sử': 'View history',
    'Trang quản trị': 'Admin dashboard',
    'Quản lý tổng quan dữ liệu, khách hàng và chỉ số điện trong hệ thống.': 'Manage system data, customers, and meter readings in one place.',
    'Tổng doanh thu': 'Total revenue',
    'Tổng hộ dân': 'Total households',
    'Điện tiêu thụ': 'Energy consumed',
    'Quản lý khách hàng': 'Customer management',
    'Thêm khách hàng': 'Add customer',
    'Sửa thông tin khách hàng': 'Edit customer information',
    'Xóa thông tin khách hàng': 'Delete customer information',
    'Nhập chỉ số điện': 'Enter meter readings',
    'Giá điện': 'Electricity tariff',
    'Cập nhật giá điện': 'Update tariff',
    'Phân tích BigData': 'Big Data analytics',
    'Hiển thị nhanh các chỉ số phân tích, xu hướng tiêu thụ và khách hàng tiêu thụ cao.': 'Quick view of analytics, consumption trends, and high-usage customers.',
    'Tổng khách hàng': 'Total customers',
    'Số hộ dân đang theo dõi.': 'Households currently being monitored.',
    'Tiêu thụ trung bình 30 ngày': 'Average 30-day consumption',
    'Trung bình kWh mỗi hộ trong tháng gần nhất.': 'Average kWh per household in the latest month.',
    'Doanh thu năm nay': 'Revenue this year',
    'Tổng doanh thu từ đầu năm.': 'Total revenue since the beginning of the year.',
    'Dự báo tiền điện': 'Bill forecast',
    'Dự báo hóa đơn trung bình tháng tới theo xu hướng.': 'Forecast average bill for next month based on trends.',
    'Hộ thiếu chỉ số': 'Households missing readings',
    'Số hộ chưa cập nhật chỉ số tháng này.': 'Households that have not updated readings this month.',
    'Tải lại dữ liệu BigData': 'Refresh Big Data',
    'Phân khúc khách hàng theo tiêu thụ': 'Customer segments by consumption',
    'Xu hướng tiêu thụ 12 tháng': '12-month consumption trend',
    'Doanh thu theo tháng': 'Monthly revenue',
    'Phân khúc (Donut)': 'Segments (donut)',
    'Top 5 khách hàng tiêu thụ cao': 'Top 5 high-usage customers',
    'Mã KH': 'Customer ID',
    'Họ và tên': 'Full name',
    'Tổng kWh': 'Total kWh',
    'Phân tích nâng cao: Khách hàng tiêu thụ bất thường': 'Advanced analysis: Anomalous customers',
    'Không phát hiện khách hàng bất thường.': 'No anomalous customers found.',
    'Phân khúc tiêu thụ khách hàng': 'Customer consumption segments',
    'Phân khúc': 'Segment',
    'Số khách': 'Customers',
    'Tiêu thụ trung bình': 'Average consumption',
    'Ghi nhận chỉ số mới cho khách hàng và cập nhật lên hệ thống.': 'Record new customer readings and update the system.',
    'Nhập chỉ số': 'Enter reading',
    'Danh sách khách hàng': 'Customer list',
    'Xem nhanh các khách hàng hiện tại và tổng điện tiêu thụ của mỗi mã hộ.': 'Quickly view current customers and total consumption for each household.',
    'Địa chỉ': 'Address',
    'SĐT': 'Phone',
    'Vai trò': 'Role',
    'Thống kê doanh thu từ hóa đơn theo khoảng thời gian.': 'View invoice revenue for a selected period.',
    'Tất cả': 'All',
    'Chưa thanh toán': 'Unpaid',
    'Đã thanh toán': 'Paid',
    'Xem biểu đồ': 'View chart',
    'Danh sách hóa đơn': 'Invoice list',
    'Mã HD': 'Invoice ID',
    'Ngày tạo': 'Created date',
    'Số tiền': 'Amount',
    'Phân tích doanh thu theo tháng': 'Monthly revenue analysis',
    'Biểu đồ so sánh doanh thu qua các năm với các chỉ số phân tích chuyên sâu.': 'Compare revenue across years with advanced analytics.',
    'Quay lại': 'Back',
    'Trung bình/tháng': 'Average/month',
    'Cao nhất': 'Highest',
    'Thấp nhất': 'Lowest',
    'Xu hướng': 'Trend',
    'Tỉ lệ thay đổi': 'Change rate',
    'Biểu đồ doanh thu theo tháng': 'Monthly revenue chart',
    'So sánh doanh thu theo các năm trong khoảng thời gian đã chọn.': 'Compare revenue by year for the selected period.',
    'Đang tính toán dữ liệu...': 'Calculating data...',
    'Đếm số hộ dân theo khu vực hoặc toàn bộ danh sách.': 'Count households by area or across the full list.',
    'Khu vực ': 'Area ',
    'Nhập khu vực': 'Enter area',
    'Xem bản đồ': 'View map',
    'Bản đồ hộ dân': 'Household map',
    'Hiển thị số lượng hộ dân theo tỉnh thành; nếu chọn khu vực, hiển thị chi tiết quận/huyện.': 'Show households by province; select an area to see district details.',
    'Phân tích & Thống kê': 'Analytics & statistics',
    'Đang tải bản đồ...': 'Loading map...',
    'Hướng dẫn': 'Guide',
    'Di chuột lên tỉnh/thành để xem số hộ và chi tiết quận/huyện.': 'Hover over a province to see household and district details.',
    'Chi tiết vùng': 'Region details',
    'Di chuột lên tỉnh/thành trên bản đồ để xem số hộ dân.': 'Hover over a province on the map to see households.',
    'Quận/Huyện trong vùng': 'Districts in the region',
    'Phân tích hộ dân & Big Data': 'Household & Big Data analysis',
    'Thống kê dân cư theo khu vực với biểu đồ trực quan và phân tích chuyên sâu.': 'Analyze households by area with visual charts and advanced insights.',
    'Số tỉnh/thành': 'Provinces/cities',
    'Số lượng tỉnh/thành có hộ dân được ghi nhận.': 'Number of provinces/cities with recorded households.',
    'Trung bình/tỉnh': 'Average/province',
    'Mức hộ trung bình trên mỗi tỉnh/thành.': 'Average households per province/city.',
    'Tỉnh dẫn đầu': 'Leading province',
    'Tỉnh/thành chiếm tỷ trọng lớn nhất.': 'Province/city with the largest share.',
    'Thị phần lớn nhất': 'Largest share',
    'Hộ dân theo tỉnh/thành': 'Households by province/city',
    'Phân tích khu vực': 'Regional analysis',
    'Tiềm năng:': 'Potential:',
    'Khó khăn:': 'Challenges:',
    'Thử thách:': 'Risks:',
    'Điện tiêu thụ': 'Energy consumption',
    'Xem tổng điện tiêu thụ theo mã hộ hoặc toàn bộ khách hàng.': 'View consumption by household ID or across all customers.',
    'Mã hộ ': 'Household ID ',
    'Nhập mã hộ/ bỏ trống nếu muốn xem tổng tiêu thụ của tất cả khách hàng': 'Enter a household ID, or leave blank for all customers',
    'Phân tích tiêu thụ điện': 'Energy consumption analysis',
    'Biểu đồ sản lượng điện theo tháng và phân tích chuyên sâu theo mô hình BigData.': 'Monthly energy output chart with advanced Big Data analysis.',
    'Biểu đồ sản lượng điện theo tháng': 'Monthly energy output chart',
    'So sánh sản lượng điện tiêu thụ theo các tháng và phân tích xu hướng.': 'Compare monthly energy consumption and analyze trends.',
    'Đang tải biểu đồ...': 'Loading chart...',
    'Tải lại phân tích': 'Refresh analysis',
    'Cập nhật giá theo bậc tiêu thụ và ngày áp dụng mới.': 'Update tariffs by consumption tier and effective date.',
    'Ngày áp dụng': 'Effective date',
    'Ghi chú': 'Notes',
    'Ví dụ: Điều chỉnh giá từ tháng 06/2026': 'Example: Adjust tariff from June 2026',
    'Bảng giá hiện tại': 'Current tariff',
    'Giá hiện tại áp dụng từ 2026-01-01.': 'Current tariffs apply from 2026-01-01.',
    'Bậc': 'Tier',
    'Giới hạn (kWh)': 'Limit (kWh)',
    'Đơn giá mới (đ/kWh)': 'New rate (VND/kWh)',
    'Nhập chỉ số điện mới': 'Enter new meter readings',
    'Chọn tháng và năm cần nhập chỉ số, sau đó hệ thống sẽ hiển thị mã KH, tên, địa chỉ, SĐT, chỉ số tháng trước và chỉ số tháng cần nhập.': 'Select the month and year, then the system will show customer details, the previous reading, and the new reading.',
    'Chọn tháng': 'Select month',
    '-- Chọn tháng --': '-- Select month --',
    'Nhập mã KH nếu nhập riêng': 'Enter customer ID for an individual reading',
    'Chỉ số tháng trước': 'Previous month reading',
    'Chỉ số tháng cần nhập': 'Reading to enter',
    'Nhập mã khách hàng': 'Enter customer ID',
    'Nhập họ và tên': 'Enter full name',
    'Nhập email': 'Enter email',
    'Nhập số điện thoại': 'Enter phone number',
    'Nhập địa chỉ': 'Enter address',
    'Cập nhật nhanh thông tin khách hàng theo mã KH.': 'Quickly update customer information by customer ID.',
    'Cập nhật họ và tên': 'Update full name',
    'Cập nhật email': 'Update email',
    'Cập nhật số điện thoại': 'Update phone number',
    'Cập nhật mật khẩu': 'Update password',
    'Cập nhật địa chỉ': 'Update address',
    'Nhập mã khách hàng cần xóa khỏi hệ thống.': 'Enter the customer ID to remove from the system.',
    'Nhập mã khách hàng cần xóa': 'Enter customer ID to delete',
    'Phân tích hộ dân & Big Data': 'Household & Big Data analysis',
    'Khám phá quy mô hộ dân và mức độ tập trung theo tỉnh thành.': 'Explore household scale and concentration by province or city.',
    'Phạm vi tra cứu:': 'Search scope:',
    'Toàn quốc': 'Nationwide',
    'Tỉnh / thành': 'Provinces / cities',
    'Trung bình / tỉnh': 'Average / province',
    'Dẫn đầu': 'Leading area',
    'Thị phần cao nhất': 'Largest share',
    'Quay lại bản đồ': 'Back to map',
    'Tỷ trọng hộ dân theo tỉnh / thành': 'Household share by province / city',
    'Tỷ lệ đóng góp trong phạm vi tra cứu hiện tại': 'Share within the current search scope',
    'Xếp hạng khu vực': 'Regional ranking',
    'Các tỉnh / thành có quy mô hộ dân cao nhất': 'Provinces / cities with the most households',
    'Tiềm năng': 'Potential',
    'Khó khăn': 'Challenges',
    'Thử thách': 'Risks',
    '© 2026 Trọng Thành - Trang quản trị hệ thống.': '© 2026 Trọng Thành - System administration portal.',
    'Hỗ trợ khách hàng': 'Customer support',
    'Giới thiệu': 'About us',
    'Tầm nhìn - Sứ mệnh': 'Vision - Mission',
    'Tuyển dụng': 'Careers',
    'Hướng dẫn tra cứu': 'Lookup guide',
    'Hướng dẫn thanh toán': 'Payment guide',
    'Câu hỏi thường gặp': 'Frequently asked questions',
    'Liên hệ': 'Contact',
    'Kết nối': 'Connect',
    'Trang quản lý tiền điện.': 'Electricity billing management portal.',
    '© 2026 Trọng Thành - Trang quản lý tiền điện. ': '© 2026 Trọng Thành - Electricity billing management portal. '
  };

  function translateValue(value, language) {
    if (language !== 'en') return value;
    const trimmed = value.trim();
    const translated = translations[trimmed];
    if (translated === undefined && /^Giá hiện tại áp dụng từ .+\.$/.test(trimmed)) {
      return value.replace(trimmed, `Current tariffs apply from ${trimmed.slice(24)}`);
    }
    return translated === undefined ? value : value.replace(trimmed, translated);
  }

  function translatePage(language) {
    document.documentElement.lang = language === 'en' ? 'en' : 'vi';
    if (!document.documentElement.__i18nTitle) document.documentElement.__i18nTitle = document.title;
    document.title = translateValue(document.documentElement.__i18nTitle, language);

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);
    textNodes.forEach((node) => {
      if (node.parentElement.closest('script, style, .language-switcher')) return;
      if (node.__i18nOriginal === undefined) node.__i18nOriginal = node.nodeValue;
      node.nodeValue = translateValue(node.__i18nOriginal, language);
    });

    document.querySelectorAll('[placeholder], [aria-label], [title]').forEach((element) => {
      ['placeholder', 'aria-label', 'title'].forEach((attribute) => {
        if (element.hasAttribute(attribute)) {
          const key = `__i18n_${attribute}`;
          if (element[key] === undefined) element[key] = element.getAttribute(attribute);
          element.setAttribute(attribute, translateValue(element[key], language));
        }
      });
    });

    const button = document.querySelector('.language-switcher__button');
    if (button) {
      const label = language === 'en' ? 'English' : 'Tiếng Việt';
      const labelElement = button.querySelector('[data-language-label]');
      if (labelElement.textContent !== label) labelElement.textContent = label;
      button.setAttribute('aria-label', language === 'en' ? 'Choose language' : 'Chọn ngôn ngữ');
    }
  }

  function mountSwitcher() {
    if (document.querySelector('.language-switcher')) return;
    const host = document.querySelector('.header-top-right, .header-inner, .top-utility');
    if (!host) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'language-switcher';
    wrapper.innerHTML = '<button class="language-switcher__button" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Chọn ngôn ngữ"><span class="language-switcher__globe" aria-hidden="true">◎</span><span data-language-label>Tiếng Việt</span><span class="language-switcher__chevron" aria-hidden="true">⌄</span></button><div class="language-switcher__menu" role="menu"><button type="button" role="menuitem" data-language="vi"><span>VI</span> Tiếng Việt</button><button type="button" role="menuitem" data-language="en"><span>EN</span> English</button></div>';
    host.appendChild(wrapper);

    const button = wrapper.querySelector('.language-switcher__button');
    button.addEventListener('click', () => {
      const isOpen = wrapper.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(isOpen));
    });
    wrapper.querySelectorAll('[data-language]').forEach((option) => {
      option.addEventListener('click', () => {
        const language = option.dataset.language;
        localStorage.setItem('quanlytiendien-language', language);
        wrapper.classList.remove('is-open');
        translatePage(language);
      });
    });
    document.addEventListener('click', (event) => {
      if (!wrapper.contains(event.target)) {
        wrapper.classList.remove('is-open');
        button.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function init() {
    mountSwitcher();
    const language = localStorage.getItem('quanlytiendien-language') || 'vi';
    translatePage(language);
    const observer = new MutationObserver(() => {
      translatePage(localStorage.getItem('quanlytiendien-language') || 'vi');
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

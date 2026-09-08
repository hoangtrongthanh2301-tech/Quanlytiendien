(function () {
  const current = document.getElementById('customerSecurityCurrentPassword');
  const faceCurrent = document.getElementById('customerFaceSecurityCurrentPassword');
  if (!current || !faceCurrent) return;
  const status = document.getElementById('customerSecurityStatus');
  const facePasswordStatus = document.getElementById('customerFacePasswordStatus');
  const passwordStatus = document.getElementById('passwordChangeStatus');
  const camera = document.getElementById('customerFaceCamera');
  const faceStart = document.getElementById('customerFaceStart');
  const faceRemove = document.getElementById('customerFaceRemove');
  const facePasswordForm = document.getElementById('customerFacePasswordForm');
  const faceScanForm = document.getElementById('customerFaceScanForm');
  const faceHistory = document.getElementById('faceHistory');
  const registrationStatus = document.getElementById('faceRegistrationStatus');
  const registrationDot = document.getElementById('faceStatusDot');
  const faceStatusText = document.getElementById('faceStatusText');
  let stream = null;
  let modelsReady = false;
  const modelUrl = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';

  function message(text, error) {
    status.textContent = text;
    status.className = `security-status ${error ? 'error' : 'ok'}`;
  }

  function passwordMessage(text, error) {
    passwordStatus.textContent = text;
    passwordStatus.className = `security-status ${error ? 'error' : 'ok'}`;
  }

  function facePasswordMessage(text, error) {
    facePasswordStatus.textContent = text;
    facePasswordStatus.className = `security-status ${error ? 'error' : 'ok'}`;
  }

  async function request(payload) {
    const response = await fetch('face-auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Không thể xử lý yêu cầu.');
    return result;
  }

  async function loadFaceStatus() {
    try {
      const result = await request({ action: 'status' });
      const registered = Boolean(result.registered);
      registrationStatus.textContent = result.registered_at ? `Đã đăng ký lúc ${result.registered_at}` : 'Đã đăng ký';
      faceHistory.hidden = !registered;
      faceStatusText.textContent = registered ? 'Đã có đăng ký khuôn mặt' : 'Chưa đăng ký khuôn mặt';
      registrationDot.classList.toggle('active', registered);
      faceRemove.disabled = !registered;
    } catch (error) {
      registrationStatus.textContent = 'Không thể tải trạng thái bảo mật';
    }
  }

  async function startCamera() {
    try {
      faceStart.disabled = true;
      message('Đang tải mô hình nhận diện...');
      if (!modelsReady) {
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
          faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl),
          faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl)
        ]);
        modelsReady = true;
      }
      if (stream) stream.getTracks().forEach(track => track.stop());
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
      camera.srcObject = stream;
      await new Promise(function (resolve) {
        if (camera.readyState >= 2) { resolve(); return; }
        camera.addEventListener('loadeddata', resolve, { once: true });
      });
      message('Camera sẵn sàng. Hãy nhìn thẳng và di chuyển đầu nhẹ.');
    } finally { faceStart.disabled = false; }
  }

  async function capture() {
    const samples = [];
    let previousBox = null;
    for (let index = 0; index < 3; index += 1) {
      const detection = await faceapi.detectSingleFace(camera, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.75 })).withFaceLandmarks().withFaceDescriptor();
      if (!detection) throw new Error('Không phát hiện khuôn mặt rõ ràng.');
      const box = detection.detection.box;
      if (previousBox && Math.abs(box.x - previousBox.x) < 2 && Math.abs(box.y - previousBox.y) < 2) throw new Error('Hãy di chuyển đầu nhẹ để kiểm tra hiện diện.');
      previousBox = box;
      samples.push(Array.from(detection.descriptor));
      message(`Đã kiểm tra ${index + 1}/3...`);
      await new Promise(resolve => setTimeout(resolve, 350));
    }
    return samples[0].map((value, index) => samples.reduce((sum, sample) => sum + sample[index], 0) / samples.length);
  }

  document.getElementById('customerSecurityNewPassword').addEventListener('input', function () {
    const value = this.value;
    let score = 0;
    if (value.length >= 6) score += 1;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;
    if (/[^A-Za-z0-9]/.test(value)) score += 1;
    const strength = document.getElementById('securityPasswordStrength');
    strength.style.width = `${score * 25}%`;
    strength.style.background = score < 2 ? '#dc2626' : score < 4 ? '#d97706' : '#0f766e';
  });

  document.querySelectorAll('.security-eye').forEach(function (button) {
    button.addEventListener('click', function () {
      const input = document.getElementById(button.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
      button.setAttribute('aria-label', input.type === 'password' ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
  });

  document.getElementById('customerPasswordForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const newPassword = document.getElementById('customerSecurityNewPassword').value;
    const confirm = document.getElementById('customerSecurityConfirmPassword').value;
    if (newPassword !== confirm) { passwordMessage('Mật khẩu xác nhận không khớp.', true); return; }
    if (newPassword.length < 6) { passwordMessage('Mật khẩu mới phải có ít nhất 6 ký tự.', true); return; }
    try {
      const result = await request({ action: 'change_password', current_password: current.value, new_password: newPassword });
      passwordMessage(result.message);
      current.value = '';
      document.getElementById('customerSecurityNewPassword').value = '';
      document.getElementById('customerSecurityConfirmPassword').value = '';
    } catch (error) { passwordMessage(error.message, true); }
  });

  facePasswordForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!faceCurrent.value) { facePasswordMessage('Vui lòng nhập mật khẩu hiện tại.', true); return; }
    try {
      faceStart.disabled = true;
      await request({ action: 'verify_password', current_password: faceCurrent.value });
      facePasswordMessage('Mật khẩu chính xác. Đang chuyển sang quét khuôn mặt...');
      facePasswordForm.hidden = true;
      faceScanForm.hidden = false;
      await startCamera();
      const descriptor = await capture();
      const result = await request({ action: 'register', current_password: faceCurrent.value, descriptor });
      message(result.message);
      await loadFaceStatus();
    } catch (error) {
      facePasswordForm.hidden = false;
      faceScanForm.hidden = true;
      facePasswordMessage(error.message, true);
    } finally { faceStart.disabled = false; }
  });
  faceRemove.addEventListener('click', async function () {
    if (!window.confirm('Bạn có chắc muốn xóa đăng ký khuôn mặt?')) return;
    try {
      const result = await request({ action: 'remove_face', current_password: faceCurrent.value });
      message(result.message);
      await loadFaceStatus();
    } catch (error) { message(error.message, true); }
  });

  loadFaceStatus();
  window.addEventListener('beforeunload', function () {
    if (stream) stream.getTracks().forEach(track => track.stop());
  });
})();

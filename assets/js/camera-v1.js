/**
 * Version 1 — Camera capture & file upload handling.
 * Allows users to either take a photo directly with the live camera
 * or upload an existing image file from their device.
 */
(function () {
  const tabCamera = document.getElementById('tabCamera');
  const tabUpload = document.getElementById('tabUpload');
  const cameraPane = document.getElementById('cameraPane');
  const uploadPane = document.getElementById('uploadPane');
  const openCameraBtn = document.getElementById('openCameraBtn');
  const photoBoxCamera = document.getElementById('photoBoxCamera');
  const cameraError = document.getElementById('cameraError');
  const cameraPhotoData = document.getElementById('cameraPhotoData');
  const fileInput = document.getElementById('fileInput');
  const filePreviewBox = document.getElementById('filePreviewBox');
  const filePreviewImg = document.getElementById('filePreviewImg');
  const uploadPrompt = document.getElementById('uploadPrompt');
  const form = document.getElementById('v1Form');

  if (!tabCamera || !tabUpload) return;

  let stream = null;

  function stopCamera() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
  }

  function setMode(mode) {
    if (mode === 'camera') {
      tabCamera.className = 'btn btn-primary btn-sm';
      tabUpload.className = 'btn btn-outline btn-sm';
      cameraPane.style.display = 'block';
      uploadPane.style.display = 'none';
    } else {
      tabCamera.className = 'btn btn-outline btn-sm';
      tabUpload.className = 'btn btn-primary btn-sm';
      cameraPane.style.display = 'none';
      uploadPane.style.display = 'block';
      stopCamera();
    }
  }

  tabCamera.addEventListener('click', () => setMode('camera'));
  tabUpload.addEventListener('click', () => setMode('upload'));

  function resetCameraUI() {
    photoBoxCamera.innerHTML = `
      <div class="photo-empty">
        <p>📷 Open your camera to take a photo of the meter.</p>
      </div>
      <div style="padding:0 10px 10px;">
        <button type="button" class="btn btn-primary" id="openCameraBtn">📷 Open live camera</button>
      </div>
    `;
    const newBtn = document.getElementById('openCameraBtn');
    if (newBtn) newBtn.addEventListener('click', openCamera);
  }

  async function openCamera() {
    if (cameraError) cameraError.style.display = 'none';
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
      });
    } catch (err) {
      if (cameraError) {
        cameraError.textContent = 'Camera access was blocked or unavailable. Check camera permissions or switch to the "Upload File" tab.';
        cameraError.style.display = 'block';
      }
      return;
    }

    photoBoxCamera.innerHTML = `
      <span class="live-tag"><span class="live-dot"></span>LIVE</span>
      <video id="liveVideoV1" autoplay playsinline muted></video>
      <div class="cam-controls">
        <button type="button" class="btn btn-amber" id="captureBtnV1" style="flex:1">📸 Snap photo</button>
      </div>
    `;

    const video = document.getElementById('liveVideoV1');
    video.srcObject = stream;
    await video.play().catch(() => {});

    document.getElementById('captureBtnV1').addEventListener('click', () => {
      if (!video || !video.videoWidth) return;
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

      cameraPhotoData.value = dataUrl;

      // Clear file upload input so camera photo is preferred
      if (fileInput) fileInput.value = '';
      if (filePreviewBox) filePreviewBox.style.display = 'none';
      if (uploadPrompt) uploadPrompt.style.display = 'block';

      stopCamera();

      photoBoxCamera.innerHTML = `
        <img class="captured-img" src="${dataUrl}">
        <div class="retake-row">
          <button type="button" class="btn btn-outline btn-sm" id="retakeBtnV1" style="flex:1">↻ Retake photo</button>
        </div>
      `;

      document.getElementById('retakeBtnV1').addEventListener('click', () => {
        cameraPhotoData.value = '';
        openCamera();
      });
    });
  }

  if (openCameraBtn) {
    openCameraBtn.addEventListener('click', openCamera);
  }

  // File input preview & clear camera state
  if (fileInput) {
    fileInput.addEventListener('change', () => {
      if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        const reader = new FileReader();
        reader.onload = (e) => {
          filePreviewImg.src = e.target.result;
          filePreviewBox.style.display = 'block';
          if (uploadPrompt) uploadPrompt.style.display = 'none';

          // Clear camera photo
          cameraPhotoData.value = '';
          resetCameraUI();
        };
        reader.readAsDataURL(file);
      } else {
        filePreviewBox.style.display = 'none';
        if (uploadPrompt) uploadPrompt.style.display = 'block';
      }
    });
  }

  // Client validation on submit
  if (form) {
    form.addEventListener('submit', (e) => {
      const hasCameraPhoto = !!cameraPhotoData.value;
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
      if (!hasCameraPhoto && !hasFile) {
        e.preventDefault();
        alert('Please take a photo with the camera or upload an image file of the meter before submitting.');
      }
    });
  }

  window.addEventListener('beforeunload', stopCamera);
})();

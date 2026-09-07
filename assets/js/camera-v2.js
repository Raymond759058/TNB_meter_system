/**
 * Version 2 — forces a live camera capture for the meter photo.
 * There is intentionally no <input type="file"> anywhere in this flow,
 * so there is no gallery/file-browsing path in the UI. The server also
 * rejects any submission with an empty live_photo_data field, so this is
 * enforced even if someone tampers with the disabled attributes client-side.
 */
(function () {
  const openBtn = document.getElementById('openCameraBtn');
  const photoBox = document.getElementById('photoBox');
  const errorEl = document.getElementById('cameraError');
  const hiddenInput = document.getElementById('livePhotoData');
  const readingInput = document.getElementById('readingInput');
  const readingLockNote = document.getElementById('readingLockNote');
  const submitBtn = document.getElementById('submitBtn');
  const meterSelect = document.getElementById('meterSelect');
  const stepMeter = document.getElementById('stepMeter');
  const stepPhoto = document.getElementById('stepPhoto');
  const stepReading = document.getElementById('stepReading');

  if (!openBtn) return; // no meters assigned, form not rendered

  let stream = null;

  function updateFlow() {
    stepMeter.classList.toggle('done', !!meterSelect.value);
    stepPhoto.classList.toggle('done', !!hiddenInput.value);
    stepReading.classList.toggle('done', !!readingInput.value);
  }

  function updateSubmitState() {
    const ready = !!hiddenInput.value && !!readingInput.value;
    submitBtn.disabled = !ready;
    submitBtn.textContent = hiddenInput.value ? '✓ Submit reading' : 'Live photo required to submit';
    updateFlow();
  }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = 'block';
  }

  async function openCamera() {
    errorEl.style.display = 'none';
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    } catch (e) {
      showError('Camera access was blocked or unavailable. Allow camera permission in your browser to capture a live meter photo.');
      return;
    }

    photoBox.innerHTML = `
      <span class="live-tag"><span class="live-dot"></span>LIVE</span>
      <video id="liveVideo" autoplay playsinline muted></video>
      <div class="cam-controls">
        <button type="button" class="btn btn-amber" id="captureBtn" style="flex:1">📷 Capture live photo</button>
      </div>`;

    const video = document.getElementById('liveVideo');
    video.srcObject = stream;
    await video.play().catch(() => {});

    document.getElementById('captureBtn').addEventListener('click', captureFrame);
  }

  function stopCamera() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
  }

  function captureFrame() {
    const video = document.getElementById('liveVideo');
    if (!video || !video.videoWidth) return;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

    hiddenInput.value = dataUrl;
    stopCamera();

    photoBox.innerHTML = `
      <img class="captured-img" src="${dataUrl}">
      <div class="retake-row">
        <button type="button" class="btn btn-outline btn-sm" id="retakeBtn" style="flex:1">↻ Retake live photo</button>
      </div>`;
    document.getElementById('retakeBtn').addEventListener('click', () => {
      hiddenInput.value = '';
      updateSubmitState();
      openCamera();
    });

    readingInput.disabled = false;
    readingLockNote.style.display = 'none';
    updateSubmitState();
  }

  openBtn.addEventListener('click', openCamera);
  readingInput.addEventListener('input', updateSubmitState);
  meterSelect.addEventListener('change', updateFlow);
  window.addEventListener('beforeunload', stopCamera);

  updateSubmitState();
})();

// Simple square cropper with vertical drag (no frame, fixed width, user can drag up/down only)
document.addEventListener('DOMContentLoaded', function() {
  const editBtn = document.getElementById('edit-image-btn');
  const cancelBtn = document.getElementById('cancel-image-btn');
  const updateBtn = document.getElementById('update-image-btn');
  const fileInput = document.getElementById('profile-image-input');
  const imageDisplay = document.getElementById('profile-image-display');
  const modal = document.getElementById('hs-subscription-with-image');
  const cropperCanvas = document.getElementById('profile-cropper-canvas');

  let cropperImage = null; // Image object for cropping
  let dragging = false;
  let dragStartY = 0;
  let imageOffsetY = 0;
  let imageMinOffsetY = 0;
  let imageMaxOffsetY = 0;
  let cropperReady = false;
  let cropperImageNaturalWidth = 0;
  let cropperImageNaturalHeight = 0;
  let cropperBoxSize = 0;

  // Reset modal state
  function resetModalState() {
    editBtn.classList.remove('hidden');
    editBtn.style.display = '';
    cancelBtn.classList.add('hidden');
    cancelBtn.style.display = 'none';
    updateBtn.classList.add('hidden');
    updateBtn.style.display = 'none';
    fileInput.value = '';
    cropperCanvas.classList.add('hidden');
    cropperCanvas.style.display = 'none';
    cropperReady = false;
    // Restore original image/initials
    Array.from(imageDisplay.children).forEach(child => {
      if (child !== cropperCanvas) child.style.display = '';
    });
  }

  // Show file selected state
  function showFileSelectedState() {
    editBtn.classList.add('hidden');
    editBtn.style.display = 'none';
    cancelBtn.classList.remove('hidden');
    cancelBtn.style.display = '';
    updateBtn.classList.remove('hidden');
    updateBtn.style.display = '';
  }

  // Modal open event
  document.addEventListener('click', function(e) {
    if (e.target.closest('[data-hs-overlay="#hs-subscription-with-image"]')) {
      setTimeout(resetModalState, 50);
    }
  });

  // Edit button click
  editBtn?.addEventListener('click', function() {
    fileInput.click();
  });

  // File input change - show cropper
  fileInput?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file || !file.type.match('image/jpeg')) {
      alert('Please select a valid JPG/JPEG image file.');
      fileInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(ev) {
      cropperImage = new window.Image();
      cropperImage.onload = function() {
        cropperImageNaturalWidth = cropperImage.naturalWidth;
        cropperImageNaturalHeight = cropperImage.naturalHeight;
        // Set up cropper canvas
        cropperCanvas.width = cropperCanvas.height = cropperBoxSize = Math.min(imageDisplay.offsetWidth, imageDisplay.offsetHeight, 400);
        cropperCanvas.style.width = cropperBoxSize + 'px';
        cropperCanvas.style.height = cropperBoxSize + 'px';
        cropperCanvas.classList.remove('hidden');
        cropperCanvas.style.display = '';
        // Hide other children
        Array.from(imageDisplay.children).forEach(child => {
          if (child !== cropperCanvas) child.style.display = 'none';
        });
        // Calculate scale to fit width
        const scale = cropperBoxSize / cropperImageNaturalWidth;
        const drawHeight = cropperImageNaturalHeight * scale;
        // Center image vertically if taller than box, else top-align
        imageOffsetY = 0;
        if (drawHeight > cropperBoxSize) {
          imageOffsetY = (cropperBoxSize - drawHeight) / 2;
        }
        // Calculate min/max offset for vertical drag
        imageMinOffsetY = cropperBoxSize - drawHeight;
        imageMaxOffsetY = 0;
        cropperReady = true; // <-- FIX: set cropperReady before drawCropper
        drawCropper(); // <-- FIX: draw immediately after image loads
        showFileSelectedState();
      };
      cropperImage.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  // Draw cropper image
  function drawCropper() {
    if (!cropperImage || !cropperReady) return;
    const ctx = cropperCanvas.getContext('2d');
    ctx.clearRect(0, 0, cropperBoxSize, cropperBoxSize);
    // Always fit width, crop top/bottom if needed
    const scale = cropperBoxSize / cropperImageNaturalWidth;
    const drawHeight = cropperImageNaturalHeight * scale;
    ctx.drawImage(
      cropperImage,
      0, 0, cropperImageNaturalWidth, cropperImageNaturalHeight,
      0, imageOffsetY, cropperBoxSize, drawHeight
    );
  }

  // Mouse/touch drag events for vertical or horizontal movement (exclusive)
  let dragDirection = null; // 'vertical' or 'horizontal'
  let dragStartX = 0;

  cropperCanvas.addEventListener('mousedown', function(e) {
    if (!cropperReady) return;
    dragging = true;
    dragStartY = e.clientY;
    dragStartX = e.clientX;
    dragDirection = null;
  });

  cropperCanvas.addEventListener('touchstart', function(e) {
    if (!cropperReady) return;
    dragging = true;
    dragStartY = e.touches[0].clientY;
    dragStartX = e.touches[0].clientX;
    dragDirection = null;
  });

  window.addEventListener('mousemove', function(e) {
    if (!dragging || !cropperReady) return;
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    if (!dragDirection) {
      if (Math.abs(dx) > Math.abs(dy)) {
        dragDirection = 'horizontal';
      } else if (Math.abs(dy) > Math.abs(dx)) {
        dragDirection = 'vertical';
      } else {
        return; // Not enough movement yet
      }
    }
    if (dragDirection === 'vertical') {
      dragStartY = e.clientY;
      imageOffsetY += dy;
      if (imageOffsetY > imageMaxOffsetY) imageOffsetY = imageMaxOffsetY;
      if (imageOffsetY < imageMinOffsetY) imageOffsetY = imageMinOffsetY;
    } else if (dragDirection === 'horizontal') {
      dragStartX = e.clientX;
      // Calculate horizontal drag limits
      // Always fit height, crop left/right if needed
      const scale = cropperBoxSize / cropperImageNaturalHeight;
      const drawWidth = cropperImageNaturalWidth * scale;
      if (typeof imageOffsetX === 'undefined') imageOffsetX = 0;
      if (typeof imageMinOffsetX === 'undefined') imageMinOffsetX = cropperBoxSize - drawWidth;
      if (typeof imageMaxOffsetX === 'undefined') imageMaxOffsetX = 0;
      imageOffsetX += dx;
      if (imageOffsetX > imageMaxOffsetX) imageOffsetX = imageMaxOffsetX;
      if (imageOffsetX < imageMinOffsetX) imageOffsetX = imageMinOffsetX;
    }
    drawCropper();
  });

  window.addEventListener('touchmove', function(e) {
    if (!dragging || !cropperReady) return;
    const dx = e.touches[0].clientX - dragStartX;
    const dy = e.touches[0].clientY - dragStartY;
    if (!dragDirection) {
      if (Math.abs(dx) > Math.abs(dy)) {
        dragDirection = 'horizontal';
      } else if (Math.abs(dy) > Math.abs(dx)) {
        dragDirection = 'vertical';
      } else {
        return;
      }
    }
    if (dragDirection === 'vertical') {
      dragStartY = e.touches[0].clientY;
      imageOffsetY += dy;
      if (imageOffsetY > imageMaxOffsetY) imageOffsetY = imageMaxOffsetY;
      if (imageOffsetY < imageMinOffsetY) imageOffsetY = imageMinOffsetY;
    } else if (dragDirection === 'horizontal') {
      dragStartX = e.touches[0].clientX;
      // Calculate horizontal drag limits
      const scale = cropperBoxSize / cropperImageNaturalHeight;
      const drawWidth = cropperImageNaturalWidth * scale;
      if (typeof imageOffsetX === 'undefined') imageOffsetX = 0;
      if (typeof imageMinOffsetX === 'undefined') imageMinOffsetX = cropperBoxSize - drawWidth;
      if (typeof imageMaxOffsetX === 'undefined') imageMaxOffsetX = 0;
      imageOffsetX += dx;
      if (imageOffsetX > imageMaxOffsetX) imageOffsetX = imageMaxOffsetX;
      if (imageOffsetX < imageMinOffsetX) imageOffsetX = imageMinOffsetX;
    }
    drawCropper();
  });

  window.addEventListener('mouseup', function() {
    dragging = false;
    dragDirection = null;
  });

  window.addEventListener('touchend', function() {
    dragging = false;
    dragDirection = null;
  });

  // Modify drawCropper to support horizontal offset
  function drawCropper() {
    if (!cropperImage || !cropperReady) return;
    const ctx = cropperCanvas.getContext('2d');
    ctx.clearRect(0, 0, cropperBoxSize, cropperBoxSize);

    // If dragging horizontally, fit height; else fit width
    let scale, drawWidth, drawHeight, offsetX = 0, offsetY = imageOffsetY;
    if (dragDirection === 'horizontal') {
      scale = cropperBoxSize / cropperImageNaturalHeight;
      drawWidth = cropperImageNaturalWidth * scale;
      drawHeight = cropperBoxSize;
      offsetX = typeof imageOffsetX !== 'undefined' ? imageOffsetX : 0;
      offsetY = 0;
    } else {
      scale = cropperBoxSize / cropperImageNaturalWidth;
      drawHeight = cropperImageNaturalHeight * scale;
      drawWidth = cropperBoxSize;
      offsetX = 0;
      offsetY = imageOffsetY;
    }

    ctx.drawImage(
      cropperImage,
      0, 0, cropperImageNaturalWidth, cropperImageNaturalHeight,
      offsetX, offsetY, drawWidth, drawHeight
    );
  }

  // Update button click - upload cropped image
  updateBtn?.addEventListener('click', function() {
    if (!cropperReady || !cropperImage) {
      alert('No image selected');
      return;
    }
    // Get cropped image as JPEG blob
    cropperCanvas.toBlob(function(blob) {
      if (!blob) {
        alert('Failed to crop image.');
        return;
      }
      // Validate file size (max 2MB)
      if (blob.size > 2 * 1024 * 1024) {
        alert('Cropped image is too large. Please use a smaller image.');
        return;
      }
      // Show loading state
      updateBtn.disabled = true;
      updateBtn.innerHTML = 'Uploading...';
      // Prepare FormData
      const formData = new FormData();
      formData.append('action', 'upload_profile_image');
      formData.append('profile_image', blob, 'profile.jpg');
      // Upload
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          alert('Profile image updated successfully!');
          const timestamp = new Date().getTime();
          const newImagePath = data.image_path + '?t=' + timestamp;
          // Update all profile images on the page
          const allProfileImages = document.querySelectorAll('img[src*="assets/prof_img/"]');
          allProfileImages.forEach(img => {
            if (img.src.includes(data.image_path.replace('assets/prof_img/', ''))) {
              img.src = newImagePath;
            }
          });
          // Close modal and reset state
          if (modal && window.HSOverlay) {
            window.HSOverlay.close(modal);
          }
          resetModalState();
          setTimeout(() => {
            window.location.reload();
          }, 500);
        } else {
          alert('Upload failed: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Upload error:', error);
        alert('Upload failed. Please try again.');
      })
      .finally(() => {
        updateBtn.disabled = false;
        updateBtn.innerHTML = 'Update';
      });
    }, 'image/jpeg', 0.95);
  });

  // Initialize
  resetModalState();
});
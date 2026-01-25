(function ($, Drupal, once) {
  Drupal.behaviors.dropzoneCropper = {
    attach: function (context, settings) {
      $(
        once(
          "cropper-init",
          ".dropzone, .dropzonejs, .dropzonejs-wrapper",
          context
        )
      ).each(function () {
        let checkInterval = setInterval(() => {
          let dz = this.dropzone;
          if (!dz) return;

          clearInterval(checkInterval);

          // Get the field ID from the dropzone element
          let fieldId = $(this).attr('id');
          if (!fieldId) {
            console.log('DropzoneJS: No field ID found');
            return;
          }
          
          console.log('DropzoneJS: Field ID:', fieldId);
          console.log('DropzoneJS: Available settings:', settings.dropzonejs);
          console.log('DropzoneJS: Full settings object:', settings);

          // === PRELOAD EXISTING IMAGES ===
          // Check which field this is and load appropriate files
          if (fieldId.includes('apartment-images') || fieldId.includes('apartment_images')) {
            // Handle apartment images
            if (
              settings.dropzonejs &&
              settings.dropzonejs.apartment_images &&
              settings.dropzonejs.apartment_images.files
            ) {
              let existingFiles = settings.dropzonejs.apartment_images.files;

              existingFiles.forEach(function (file) {
                let mockFile = {
                  name: file.name,
                  size: file.size,
                  type: file.mime,
                  accepted: true,
                  fid: file.fid,
                };

                dz.emit("addedfile", mockFile);
                dz.emit("thumbnail", mockFile, file.url);
                dz.emit("complete", mockFile);

                if (mockFile.previewElement) {
                  mockFile.previewElement.setAttribute("data-fid", file.fid);
                }
              });
            }
          } else if (fieldId.includes('location-screenshot') || fieldId.includes('location_screenshot')) {
            // Handle location screenshots
            console.log('DropzoneJS: Detected location_screenshot field');
            console.log('DropzoneJS: Checking settings.dropzonejs:', settings.dropzonejs);
            console.log('DropzoneJS: Checking settings.dropzonejs.location_screenshot:', settings.dropzonejs?.location_screenshot);
            
            if (
              settings.dropzonejs &&
              settings.dropzonejs.location_screenshot &&
              settings.dropzonejs.location_screenshot.files
            ) {
              let existingFiles = settings.dropzonejs.location_screenshot.files;
              console.log('DropzoneJS: Found location_screenshot files:', existingFiles);

              existingFiles.forEach(function (file) {
                let mockFile = {
                  name: file.name,
                  size: file.size,
                  type: file.mime,
                  accepted: true,
                  fid: file.fid,
                };

                dz.emit("addedfile", mockFile);
                dz.emit("thumbnail", mockFile, file.url);
                dz.emit("complete", mockFile);

                if (mockFile.previewElement) {
                  mockFile.previewElement.setAttribute("data-fid", file.fid);
                }
              });
            }
          }

          dz.on("addedfile", function (file) {
            console.log("File Added:", file);

            let reader = new FileReader();
            reader.onload = function (e) {
              let modal = $(`
                <div class="cropper-modal">
                  <div class="cropper-container">

                    <!-- IMAGE -->
                    <img id="cropper-image" src="${e.target.result}">

                    <!-- ==== TOOLBAR ==== -->
                    <div class="cropper-controls">
                      <div class="cropper-toolbar">

                        <button data-action="move" title="Move">
                          <i class="fa fa-arrows"></i>
                        </button>

                        <button data-action="crop" title="Crop">
                          <i class="fa fa-crop"></i>
                        </button>

                        <button data-action="zoom-in" title="Zoom In">
                          <i class="fa fa-search-plus"></i>
                        </button>

                        <button data-action="zoom-out" title="Zoom Out">
                          <i class="fa fa-search-minus"></i>
                        </button>

                        <button data-action="rotate-left" title="Rotate Left">
                          <i class="fa fa-rotate-left"></i>
                        </button>

                        <button data-action="rotate-right" title="Rotate Right">
                          <i class="fa fa-rotate-right"></i>
                        </button>

                        <button data-action="flip-horizontal" title="Flip Horizontal">
                          <i class="fa fa-arrows-h"></i>
                        </button>

                        <button data-action="flip-vertical" title="Flip Vertical">
                          <i class="fa fa-arrows-v"></i>
                        </button>

                      </div>

                      <div class="cropper-actions">
                        <button class="cropper-btn cancel-btn" id="cancel-crop">Cancel</button>
                        <button class="cropper-btn" id="crop-image-btn">Crop</button>
                      </div>
                    </div>
                  </div>
                </div>
              `);

              $("body").append(modal);

              let cropper = new Cropper(
                document.getElementById("cropper-image"),
                {
                  aspectRatio: NaN, // free selection (no forced width/height)
                  viewMode: 0, // no restriction, allow full image selection
                  responsive: true,
                  autoCropArea: 1,
                  movable: true,
                  zoomable: true,
                  scalable: true,
                  rotatable: true,
                  guides: true,
                }
              );

              // --- APPLY TOOLBAR ACTIONS ---
              $(".cropper-toolbar button").on("click", function () {
                let action = $(this).data("action");

                switch (action) {
                  case "move":
                    cropper.setDragMode("move");
                    break;
                  case "crop":
                    cropper.setDragMode("crop");
                    break;
                  case "zoom-in":
                    cropper.zoom(0.1);
                    break;
                  case "zoom-out":
                    cropper.zoom(-0.1);
                    break;
                  case "rotate-left":
                    cropper.rotate(-45);
                    break;
                  case "rotate-right":
                    cropper.rotate(45);
                    break;
                  case "flip-horizontal":
                    cropper.scaleX(cropper.getData().scaleX * -1);
                    break;
                  case "flip-vertical":
                    cropper.scaleY(cropper.getData().scaleY * -1);
                    break;
                }
              });

              // CROP BUTTON
              $("#crop-image-btn").on("click", function () {
                cropper.getCroppedCanvas().toBlob(function (blob) {
                  blob.name = file.name;

                  dz.removeFile(file);
                  dz.addFile(blob);

                  modal.remove();
                });
              });

              // CANCEL BUTTON
              $("#cancel-crop").on("click", function () {
                modal.remove();
              });
            };

            reader.readAsDataURL(file);
          });
        }, 200);
      });
    },
  };
})(jQuery, Drupal, once);

(function (Drupal, once) {
  Drupal.behaviors.srQuotationDropzoneRemove = {
    attach: function (context) {

      // Handle both apartment_images and location_screenshot removal
      once('sr-quotation-dropzone-remove', '#edit-apartment-images, #edit-location-screenshot, [id*="location_screenshot"]', context)
        .forEach(function (wrapper) {

          const form = wrapper.closest('form');
          if (!form) return;

          // Determine which field this is based on the wrapper ID
          const isLocationScreenshot = wrapper.id.includes('location') || wrapper.id.includes('screenshot');
          const fieldName = isLocationScreenshot ? 'location_screenshot' : 'apartment_images';
          const inputName = fieldName + '[removed_files]';

          function getRemovedInput() {
            let input = form.querySelector(
              'input[name="' + inputName + '"]'
            );

            if (!input) {
              input = document.createElement('input');
              input.type = 'hidden';
              input.name = inputName;
              input.value = '';
              form.appendChild(input);
            }
            return input;
          }

          wrapper.addEventListener('click', function (e) {

            const removeBtn = e.target.closest('.dropzonejs-remove-icon');
            if (!removeBtn) return;

            const preview = removeBtn.closest('.dz-preview');
            if (!preview) return;

            const fid = preview.dataset.fid;
            if (!fid) return;

            console.log('Removed FID:', fid);

            const input = getRemovedInput();
            const list = input.value
              ? input.value.split(';').filter(Boolean)
              : [];

            if (!list.includes(fid)) {
              list.push(fid);
            }

            input.value = list.join(';') + ';';

            // Remove preview from UI
            preview.remove();

          }, true);
        });
    }
  };
})(Drupal, once);

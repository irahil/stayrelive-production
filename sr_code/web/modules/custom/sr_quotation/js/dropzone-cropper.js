(function ($, Drupal, once) {
  Drupal.behaviors.dropzoneOnly = {
    attach: function (context, settings) {
      once(
        "dropzone-init",
        ".dropzone, .dropzonejs, .dropzonejs-wrapper",
        context,
      ).forEach(function (element) {
        let checkInterval = setInterval(() => {
          let dz = element.dropzone;
          if (!dz) return;

          clearInterval(checkInterval);

          let fieldId = element.id;
          if (!fieldId) return;

          // ===============================
          // PRELOAD EXISTING IMAGES
          // ===============================

          let fieldKey = null;

          if (
            fieldId.includes("apartment-images") ||
            fieldId.includes("apartment_images")
          ) {
            fieldKey = "apartment_images";
          } else if (
            fieldId.includes("location-screenshot") ||
            fieldId.includes("location_screenshot")
          ) {
            fieldKey = "location_screenshot";
          }

          if (
            fieldKey &&
            settings.dropzonejs &&
            settings.dropzonejs[fieldKey] &&
            settings.dropzonejs[fieldKey].files
          ) {
            let existingFiles = settings.dropzonejs[fieldKey].files;

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
          // ===============================
          // HANDLE REMOVE IMAGE
          // ===============================
          dz.on("removedfile", function (file) {
            let fid =
              file.fid ||
              (file.previewElement &&
                file.previewElement.getAttribute("data-fid"));

            if (!fid) return;

            let hiddenInput = document.querySelector(
              '[name="images[' + fieldKey + '_removed_files]"]',
            );

            if (!hiddenInput) {
              console.log("Hidden input not found");
              return;
            }

            let existing = hiddenInput.value
              ? hiddenInput.value.split(";")
              : [];

            if (!existing.includes(String(fid))) {
              existing.push(fid);
            }

            hiddenInput.value = existing.join(";");

            console.log("Removed FIDs:", hiddenInput.value);
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

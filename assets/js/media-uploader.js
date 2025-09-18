/**
 * WordPress Media Uploader for AuthDocs Document Post Type
 *
 * @since 1.5.0
 */
(function ($) {
  "use strict";

  // Wait for both jQuery and wp.media to be available
  $(document).ready(function () {
    console.log("AuthDocs: Document ready, checking dependencies...");

    // Check if wp.media is available
    if (typeof wp === "undefined") {
      console.error("AuthDocs: wp object is not available.");
      return;
    }

    if (typeof wp.media === "undefined") {
      console.error(
        "AuthDocs: wp.media is not available. Make sure wp_enqueue_media() is called."
      );
      return;
    }

    console.log("AuthDocs: All dependencies loaded successfully");

    // Check if the upload button exists
    if ($("#authdocs_upload_button").length === 0) {
      console.log(
        "AuthDocs: Upload button not found, waiting for it to appear..."
      );
    }

    var file_frame;

    // Handle upload button click
    $(document).on("click", "#authdocs_upload_button", function (e) {
      e.preventDefault();

      console.log("AuthDocs: Upload button clicked"); // Debug log

      // If the media frame already exists, reopen it
      if (file_frame) {
        file_frame.open();
        return;
      }

      // Create the media frame
      file_frame = wp.media.frames.file_frame = wp.media({
        title: protecteddocs_media_uploader.select_document,
        button: {
          text: protecteddocs_media_uploader.use_document,
        },
        multiple: false,
        library: {
          type: [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-powerpoint",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "text/plain",
            "application/rtf",
            "application/vnd.oasis.opendocument.text",
            "application/vnd.oasis.opendocument.spreadsheet",
            "application/vnd.oasis.opendocument.presentation",
            "text/csv",
          ],
        },
      });

      // When a file is selected, run a callback
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        console.log("AuthDocs: File selected:", attachment); // Debug log

        $("#authdocs_file_id").val(attachment.id);

        // Update the preview with ACF-style layout
        var previewHtml =
          '<div class="acf-file-uploader-preview">' +
          '<div class="acf-file-uploader-preview-inner">' +
          '<div class="acf-file-uploader-preview-icon">' +
          '<span class="dashicons dashicons-media-document"></span>' +
          "</div>" +
          '<div class="acf-file-uploader-preview-info">' +
          '<div class="acf-file-uploader-preview-name">' +
          attachment.filename +
          "</div>" +
          '<div class="acf-file-uploader-preview-meta">' +
          attachment.filesizeHumanReadable +
          "</div>" +
          "</div>" +
          '<div class="acf-file-uploader-preview-actions">' +
          '<a href="' +
          attachment.url +
          '" target="_blank" class="acf-button acf-button-small">' +
          '<span class="dashicons dashicons-external"></span>' +
          protecteddocs_media_uploader.view +
          "</a>" +
          '<button type="button" class="acf-button acf-button-small acf-button-remove" id="authdocs_remove_file">' +
          '<span class="dashicons dashicons-trash"></span>' +
          protecteddocs_media_uploader.remove +
          "</button>" +
          "</div>" +
          "</div>" +
          "</div>";

        $(".acf-file-uploader-inner").html(previewHtml);
      });

      // Open the media frame
      file_frame.open();
    });

    // Handle remove file button
    $(document).on("click", "#authdocs_remove_file", function (e) {
      e.preventDefault();
      $("#authdocs_file_id").val("");

      // Reset to empty state with ACF-style layout
      var emptyHtml =
        '<div class="acf-file-uploader-empty">' +
        '<div class="acf-file-uploader-empty-icon">' +
        '<span class="dashicons dashicons-cloud-upload"></span>' +
        "</div>" +
        '<div class="acf-file-uploader-empty-text">' +
        "<p>" +
        protecteddocs_media_uploader.no_file_selected +
        "</p>" +
        '<p class="description">' +
        protecteddocs_media_uploader.click_to_select +
        "</p>" +
        "</div>" +
        '<button type="button" class="acf-button acf-button-primary" id="authdocs_upload_button">' +
        '<span class="dashicons dashicons-upload"></span>' +
        protecteddocs_media_uploader.select_document +
        "</button>" +
        "</div>";

      $(".acf-file-uploader-inner").html(emptyHtml);
    });

    console.log("AuthDocs: Media uploader initialized"); // Debug log
  });
})(jQuery);

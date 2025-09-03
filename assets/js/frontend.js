jQuery(document).ready(function ($) {
  "use strict";

  // Initialize AuthDocs functionality
  initAuthDocs();

  function initAuthDocs() {
    // Handle request access button clicks
    $(document).on("click", ".authdocs-request-access-btn", function (e) {
      e.preventDefault();
      var documentId = $(this).data("document-id");
      showRequestModal(documentId);
    });

    // Handle modal close
    $(document).on("click", ".authdocs-close, .authdocs-modal", function (e) {
      if (e.target === this) {
        hideModal();
      }
    });

    // Handle form submission
    $(document).on("submit", "#authdocs-request-form", function (e) {
      e.preventDefault();
      submitAccessRequest();
    });

    // Handle escape key to close modal
    $(document).on("keydown", function (e) {
      if (e.keyCode === 27) {
        // Escape key
        hideModal();
      }
    });
  }

  function showRequestModal(documentId) {
    var modalHtml = `
            <div id="authdocs-modal" class="authdocs-modal">
                <div class="authdocs-modal-content">
                    <div class="authdocs-modal-header">
                        <span class="authdocs-close">&times;</span>
                        <h2 class="authdocs-modal-title">${
                          authdocs_ajax.strings.request_access ||
                          "Request Document Access"
                        }</h2>
                    </div>
                    <div class="authdocs-modal-body">
                        <form id="authdocs-request-form" data-document-id="${documentId}">
                            <div class="authdocs-form-group">
                                <label for="authdocs-name">${
                                  authdocs_ajax.strings.name || "Full Name"
                                }</label>
                                <input type="text" id="authdocs-name" name="name" required>
                            </div>
                            <div class="authdocs-form-group">
                                <label for="authdocs-email">${
                                  authdocs_ajax.strings.email || "Email Address"
                                }</label>
                                <input type="email" id="authdocs-email" name="email" required>
                            </div>
                        </form>
                    </div>
                    <div class="authdocs-modal-footer">
                        <button type="button" class="authdocs-btn authdocs-btn-secondary" onclick="hideModal()">
                            ${authdocs_ajax.strings.cancel || "Cancel"}
                        </button>
                        <button type="submit" form="authdocs-request-form" class="authdocs-btn authdocs-btn-primary">
                            ${
                              authdocs_ajax.strings.submit_request ||
                              "Submit Request"
                            }
                        </button>
                    </div>
                </div>
            </div>
        `;

    $("body").append(modalHtml);
    $("#authdocs-modal").show();
    $("#authdocs-name").focus();
  }

  function hideModal() {
    $("#authdocs-modal").remove();
  }

  function submitAccessRequest() {
    var $form = $("#authdocs-request-form");
    var $submitBtn = $form.find('button[type="submit"]');
    var documentId = $form.data("document-id");
    var name = $("#authdocs-name").val().trim();
    var email = $("#authdocs-email").val().trim();

    // Basic validation
    if (!name || !email) {
      showMessage(
        authdocs_ajax.strings.fill_required ||
          "Please fill in all required fields.",
        "error"
      );
      return;
    }

    if (!isValidEmail(email)) {
      showMessage(
        authdocs_ajax.strings.invalid_email ||
          "Please enter a valid email address.",
        "error"
      );
      return;
    }

    // Disable form and show loading
    $form.addClass("authdocs-loading");
    $submitBtn
      .prop("disabled", true)
      .text(authdocs_ajax.strings.submitting || "Submitting...");

    // Submit request
    $.ajax({
      url: authdocs_ajax.ajax_url,
      type: "POST",
      data: {
        action: "authdocs_request_access",
        document_id: documentId,
        name: name,
        email: email,
        nonce: authdocs_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          showMessage(
            authdocs_ajax.strings.request_sent || "Request sent successfully!",
            "success"
          );
          hideModal();
        } else {
          showMessage(
            response.data ||
              authdocs_ajax.strings.error ||
              "An error occurred. Please try again.",
            "error"
          );
        }
      },
      error: function () {
        showMessage(
          authdocs_ajax.strings.network_error ||
            "Network error. Please try again.",
          "error"
        );
      },
      complete: function () {
        $form.removeClass("authdocs-loading");
        $submitBtn
          .prop("disabled", false)
          .text(authdocs_ajax.strings.submit_request || "Submit Request");
      },
    });
  }

  function showMessage(message, type) {
    var messageClass =
      type === "success"
        ? "authdocs-message-success"
        : "authdocs-message-error";
    var messageHtml = `<div class="authdocs-message ${messageClass}">${message}</div>`;

    // Remove existing messages
    $(".authdocs-message").remove();

    // Add new message
    $(".authdocs-document").first().before(messageHtml);

    // Auto-hide after 5 seconds
    setTimeout(function () {
      $(".authdocs-message").fadeOut(function () {
        $(this).remove();
      });
    }, 5000);
  }

  function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  // Make hideModal globally available
  window.hideModal = hideModal;
});

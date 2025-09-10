(function ($) {
  "use strict";

  // Ensure jQuery is available and document is ready
  if (typeof $ === "undefined") {
    console.error("ProtectedDocs: jQuery is not available");
    return;
  }

  $(document).ready(function () {
    // Ensure required objects are available
    if (typeof protecteddocs_frontend === "undefined") {
      console.error("ProtectedDocs: Frontend configuration not available");
      return;
    }

    // Bot protection temporarily disabled for debugging

    // Replace Gutenberg block placeholders with actual shortcode content
    $(".authdocs-block-placeholder").each(function () {
      const $placeholder = $(this);
      const shortcode = $placeholder.data("shortcode");

      if (shortcode) {
        // Execute the shortcode via AJAX to get the content
        $.ajax({
          url: protecteddocs_frontend.ajax_url,
          type: "POST",
          data: {
            action: "protecteddocs_render_shortcode",
            shortcode: shortcode,
            nonce: protecteddocs_frontend.nonce,
          },
          success: function (response) {
            if (response.success) {
              $placeholder.replaceWith(response.data.html);
            }
          },
          error: function () {
            $placeholder.html("<p>Error loading content</p>");
          },
        });
      }
    });

    // Get limit from container data attributes
    function getLimit($container, type = "limit") {
      let limit;

      if (type === "limit") {
        limit = $container.data("limit") || 12;
      } else if (type === "load-more") {
        limit = $container.data("load-more-limit") || 12;
      }

      return parseInt(limit) || 12;
    }

    // Apply dynamic CSS from AJAX responses
    function applyDynamicCSS(css) {
      // Remove any existing dynamic CSS
      $("#authdocs-dynamic-ajax").remove();

      // Remove any existing instance-specific CSS that might conflict
      $("style[id^='authdocs-dynamic-']").remove();

      // Add new CSS with higher specificity
      $("<style>")
        .attr("id", "authdocs-dynamic-ajax")
        .attr("type", "text/css")
        .html(css)
        .appendTo("head");
    }

    // Apply popup color palette
    function applyPopupColorPalette(colorPalette) {
      // Color palette definitions
      const palettes = {
        default: {
          primary: "#2271b1",
          secondary: "#ffffff",
          text: "#1d2327",
          text_secondary: "#646970",
          background: "#ffffff",
          background_secondary: "#f6f7f7",
          border: "#e5e5e5",
          border_radius: "6px",
          shadow: "0 2px 4px rgba(0, 0, 0, 0.1)",
        },
        black_white_blue: {
          primary: "#2563eb",
          secondary: "#ffffff",
          text: "#000000",
          text_secondary: "#666666",
          background: "#ffffff",
          background_secondary: "#f9f9f9",
          border: "#e5e5e5",
          border_radius: "4px",
          shadow: "0 2px 4px rgba(0, 0, 0, 0.1)",
        },
        black_gray: {
          primary: "#374151",
          secondary: "#f9fafb",
          text: "#111827",
          text_secondary: "#6b7280",
          background: "#ffffff",
          background_secondary: "#f3f4f6",
          border: "#d1d5db",
          border_radius: "4px",
          shadow: "0 2px 4px rgba(0, 0, 0, 0.1)",
        },
      };

      const palette = palettes[colorPalette];
      if (!palette) return;

      // Convert hex to RGB
      function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result
          ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(
              result[3],
              16
            )}`
          : "0, 0, 0";
      }

      // Generate CSS for popup
      const css = `
      #authdocs-request-modal .authdocs-modal-card {
        background: ${palette.background} !important;
        border: 1px solid ${palette.border} !important;
        border-radius: ${palette.border_radius} !important;
        box-shadow: ${palette.shadow} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-header {
        border-bottom: 1px solid ${palette.border} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-icon {
        background: ${palette.primary} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-title {
        color: ${palette.text} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-close {
        background: ${palette.background_secondary} !important;
        color: ${palette.text_secondary} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-close:hover {
        background: ${palette.border} !important;
        color: ${palette.text} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-description {
        color: ${palette.text_secondary} !important;
      }
      
      #authdocs-request-modal .authdocs-form-label {
        color: ${palette.text} !important;
      }
      
      #authdocs-request-modal .authdocs-form-label svg {
        color: ${palette.text_secondary} !important;
      }
      
      #authdocs-request-modal .authdocs-form-input {
        background: ${palette.background} !important;
        color: ${palette.text} !important;
        border: 2px solid ${palette.border} !important;
        border-radius: ${palette.border_radius} !important;
      }
      
      #authdocs-request-modal .authdocs-form-input:focus {
        border-color: ${palette.primary} !important;
        box-shadow: 0 0 0 3px rgba(${hexToRgb(
          palette.primary
        )}, 0.1) !important;
      }
      
      #authdocs-request-modal .authdocs-form-input::placeholder {
        color: ${palette.text_secondary} !important;
      }
      
      #authdocs-request-modal .authdocs-modal-footer {
        border-top: 1px solid ${palette.border} !important;
      }
      
      #authdocs-request-modal .authdocs-btn-primary {
        background: ${palette.primary} !important;
        color: ${palette.secondary} !important;
        border: 1px solid ${palette.primary} !important;
        border-radius: ${palette.border_radius} !important;
      }
      
      #authdocs-request-modal .authdocs-btn-primary:hover {
        background: ${palette.text} !important;
        color: ${palette.background} !important;
        border-color: ${palette.text} !important;
      }
      
      #authdocs-request-modal .authdocs-btn-outline {
        background: transparent !important;
        color: ${palette.text_secondary} !important;
        border: 2px solid ${palette.border} !important;
        border-radius: ${palette.border_radius} !important;
      }
      
      #authdocs-request-modal .authdocs-btn-outline:hover {
        background: ${palette.background_secondary} !important;
        border-color: ${palette.text_secondary} !important;
        color: ${palette.text} !important;
      }
    `;

      // Remove any existing popup CSS
      $("#authdocs-popup-dynamic").remove();

      // Add new CSS
      $("<style>")
        .attr("id", "authdocs-popup-dynamic")
        .attr("type", "text/css")
        .html(css)
        .appendTo("head");
    }

    // Handle request access button clicks
    $(document).on("click", ".authdocs-request-access-btn", function (e) {
      e.preventDefault();
      const documentId = $(this).data("document-id");
      const $container = $(this).closest(".authdocs-grid-container");
      const colorPalette = $container.data("color-palette") || "default";
      showRequestAccessModal(documentId, colorPalette);
    });

    // Handle load more button clicks
    $(document).on("click", ".authdocs-load-more-btn", function (e) {
      e.preventDefault();
      const $btn = $(this);
      const $container = $btn.closest(".authdocs-grid-container");
      const currentLimit = parseInt($btn.data("current-limit"));
      const restriction = $btn.data("restriction");
      const featuredImage = $btn.data("featured-image");
      const colorPalette = $container.data("color-palette") || "default";
      const $grid = $btn.closest(".authdocs-load-more").prev(".authdocs-grid");

      // Get load more limit
      const loadMoreLimit = getLimit($container, "load-more");

      loadMoreDocuments(
        $grid,
        currentLimit,
        restriction,
        $btn,
        featuredImage,
        loadMoreLimit,
        colorPalette
      );
    });

    // Handle pagination button clicks
    $(document).on("click", ".authdocs-pagination-btn", function (e) {
      const $btn = $(this);
      const $container = $btn.closest(".authdocs-grid-container");
      const $pagination = $btn.closest(".authdocs-pagination");

      // Check pagination type from data attribute
      const paginationType = $pagination.data("pagination-type");

      // If this is classic pagination (no AJAX), let the link work normally
      if (paginationType === "classic" || ($btn.is("a") && $btn.attr("href"))) {
        // Let the link work normally for classic pagination
        return;
      }

      // Only prevent default and use AJAX for AJAX pagination
      e.preventDefault();
      const page = parseInt($btn.data("page"));

      if (page && $container.length) {
        loadPageDocuments($container, page);
      }
    });

    // Show request access modal
    function showRequestAccessModal(documentId, colorPalette = "default") {
      const modalHtml = `
            <div class="authdocs-modal" id="authdocs-request-modal" data-color-palette="${colorPalette}">
                <div class="authdocs-modal-backdrop"></div>
                <div class="authdocs-modal-container">
                    <div class="authdocs-modal-card">
                        <div class="authdocs-modal-header">
                            <div class="authdocs-modal-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </div>
                            <h2 class="authdocs-modal-title">${
                              protecteddocs_frontend.request_access_title ||
                              "Request Document Access"
                            }</h2>
                            <button type="button" class="authdocs-modal-close" aria-label="Close">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="authdocs-modal-body">
                            <p class="authdocs-modal-description">
                                Please provide your details to request access to this document.
                            </p>
                            
                            <form id="authdocs-request-form" class="authdocs-form">
                                <div class="authdocs-form-group">
                                    <label for="authdocs-name" class="authdocs-form-label">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                        ${
                                          protecteddocs_frontend.name_label ||
                                          "Full Name"
                                        }
                                    </label>
                                    <input type="text" id="authdocs-name" name="name" class="authdocs-form-input" placeholder="Enter your full name" required>
                                </div>
                                
                                <div class="authdocs-form-group">
                                    <label for="authdocs-email" class="authdocs-form-label">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                        </svg>
                                        ${
                                          protecteddocs_frontend.email_label ||
                                          "Email Address"
                                        }
                                    </label>
                                    <input type="email" id="authdocs-email" name="email" class="authdocs-form-input" placeholder="Enter your email address" required>
                                </div>
                                
                                <input type="hidden" name="document_id" value="${documentId}">
                                <input type="hidden" name="nonce" value="${
                                  protecteddocs_frontend.nonce
                                }">
                            </form>
                        </div>
                        
                        <div class="authdocs-modal-footer">
                            <button type="button" class="authdocs-btn authdocs-btn-outline authdocs-modal-close">
                                ${
                                  protecteddocs_frontend.cancel_label ||
                                  "Cancel"
                                }
                            </button>
                            <button type="button" class="authdocs-btn authdocs-btn-primary" id="authdocs-submit-request">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                                ${
                                  protecteddocs_frontend.submit_label ||
                                  "Submit Request"
                                }
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

      $("body").append(modalHtml);

      // Apply dynamic CSS for the popup based on color palette
      applyPopupColorPalette(colorPalette);

      $("#authdocs-request-modal").fadeIn(300);

      // Focus on first input
      $("#authdocs-name").focus();

      console.log(
        "ProtectedDocs: Modal created and shown, document ID:",
        documentId
      );

      // Handle close button clicks
      $("#authdocs-request-modal .authdocs-modal-close").on(
        "click",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          closeRequestModal();
        }
      );

      // Handle backdrop clicks
      $("#authdocs-request-modal .authdocs-modal-backdrop").on(
        "click",
        function (e) {
          if (e.target === this) {
            closeRequestModal();
          }
        }
      );

      // Initialize form validation
      initializeFormValidation();

      // Handle form submission
      $("#authdocs-submit-request").on("click", function (e) {
        e.preventDefault();
        console.log("ProtectedDocs: Submit button clicked");
        if (validateForm()) {
          console.log(
            "ProtectedDocs: Form validation passed, calling submitAccessRequest"
          );
          submitAccessRequest();
        } else {
          console.log("ProtectedDocs: Form validation failed");
        }
      });

      // Handle form submit event
      $("#authdocs-request-form").on("submit", function (e) {
        e.preventDefault();
        console.log("ProtectedDocs: Form submit event triggered");
        if (validateForm()) {
          console.log(
            "ProtectedDocs: Form validation passed on submit, calling submitAccessRequest"
          );
          submitAccessRequest();
        } else {
          console.log("ProtectedDocs: Form validation failed on submit");
        }
      });

      // Handle Enter key in form
      $("#authdocs-request-form").on("keypress", function (e) {
        if (e.which === 13) {
          e.preventDefault();
          console.log("ProtectedDocs: Enter key pressed in form");
          if (validateForm()) {
            console.log(
              "ProtectedDocs: Form validation passed on Enter key, calling submitAccessRequest"
            );
            submitAccessRequest();
          } else {
            console.log("ProtectedDocs: Form validation failed on Enter key");
          }
        }
      });
    }

    // Initialize form validation
    function initializeFormValidation() {
      const $nameInput = $("#authdocs-name");
      const $emailInput = $("#authdocs-email");
      const $submitBtn = $("#authdocs-submit-request");

      // Real-time validation on input
      $nameInput.on("input blur", function () {
        validateNameField($(this));
        updateSubmitButton();
      });

      $emailInput.on("input blur", function () {
        validateEmailField($(this));
        updateSubmitButton();
      });

      function updateSubmitButton() {
        const isNameValid = validateNameField($nameInput, false);
        const isEmailValid = validateEmailField($emailInput, false);
        $submitBtn.prop("disabled", !(isNameValid && isEmailValid));
      }
    }

    // Validate name field
    function validateNameField($field, showError = true) {
      const value = $field.val().trim();
      const isValid = value.length >= 2;

      console.log(
        "ProtectedDocs: Name field validation - value:",
        value,
        "isValid:",
        isValid
      );

      if (showError) {
        if (isValid) {
          clearFieldError($field);
        } else {
          showFieldError($field, "Name must be at least 2 characters long");
        }
      }

      return isValid;
    }

    // Validate email field
    function validateEmailField($field, showError = true) {
      const value = $field.val().trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const isValid = emailRegex.test(value);

      console.log(
        "ProtectedDocs: Email field validation - value:",
        value,
        "isValid:",
        isValid
      );

      if (showError) {
        if (isValid) {
          clearFieldError($field);
        } else if (value.length === 0) {
          showFieldError($field, "Email address is required");
        } else {
          showFieldError($field, "Please enter a valid email address");
        }
      }

      return isValid;
    }

    // Show field error
    function showFieldError($field, message) {
      clearFieldError($field);

      $field.addClass("authdocs-field-error");

      const $errorDiv = $(
        "<div class='authdocs-field-error-message'></div>"
      ).text(message);
      $field.after($errorDiv);
    }

    // Clear field error
    function clearFieldError($field) {
      $field.removeClass("authdocs-field-error");
      $field.siblings(".authdocs-field-error-message").remove();
    }

    // Validate entire form
    function validateForm() {
      const $nameInput = $("#authdocs-name");
      const $emailInput = $("#authdocs-email");

      const isNameValid = validateNameField($nameInput);
      const isEmailValid = validateEmailField($emailInput);

      console.log(
        "ProtectedDocs: Form validation - name valid:",
        isNameValid,
        "email valid:",
        isEmailValid
      );

      return isNameValid && isEmailValid;
    }

    // Close request modal
    function closeRequestModal() {
      $("#authdocs-request-modal").fadeOut(300, function () {
        $(this).remove();
      });
    }

    // Submit access request
    function submitAccessRequest() {
      const $form = $("#authdocs-request-form");
      const $submitBtn = $("#authdocs-submit-request");

      // Get form values directly to ensure they're captured
      const name = $("#authdocs-name").val().trim();
      const email = $("#authdocs-email").val().trim();
      const documentId = $form.find('input[name="document_id"]').val();
      const nonce = $form.find('input[name="nonce"]').val();

      console.log("ProtectedDocs: Retrieved form values:", {
        name: name,
        email: email,
        documentId: documentId,
        nonce: nonce,
      });

      // Validate required fields before submission
      if (!name || !email || !documentId) {
        console.log(
          "ProtectedDocs: Missing required fields - name:",
          name,
          "email:",
          email,
          "documentId:",
          documentId
        );
        showErrorMessage("Please fill in all required fields.");
        return;
      }

      // Create form data with only required fields
      const formData = new FormData();
      formData.append("action", "protecteddocs_request_access");
      formData.append("name", name);
      formData.append("email", email);
      formData.append("document_id", documentId);
      formData.append("nonce", nonce);

      // Debug: Log form data being sent
      console.log("ProtectedDocs: Sending form data:", {
        action: "protecteddocs_request_access",
        name: name,
        email: email,
        document_id: documentId,
        nonce: nonce,
      });

      // Debug: Show FormData contents
      console.log("ProtectedDocs: FormData contents:");
      for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
      }

      // Disable submit button and show loading
      $submitBtn
        .prop("disabled", true)
        .addClass("loading")
        .html(
          '<span class="authdocs-loading-spinner"></span>' +
            (protecteddocs_frontend.submitting_label || "Submitting...")
        );

      console.log(
        "ProtectedDocs: Making AJAX request to:",
        protecteddocs_frontend.ajax_url
      );
      console.log(
        "ProtectedDocs: protecteddocs_frontend object:",
        protecteddocs_frontend
      );

      $.ajax({
        url: protecteddocs_frontend.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function () {
          console.log("ProtectedDocs: AJAX request started");
        },
        success: function (response) {
          console.log("ProtectedDocs: AJAX success response:", response);
          console.log(
            "ProtectedDocs: Response success status:",
            response.success
          );
          console.log("ProtectedDocs: Response data:", response.data);

          if (response.success) {
            showSuccessMessage(
              response.data.message || "Access request submitted successfully!"
            );
            closeRequestModal();
          } else {
            console.log(
              "ProtectedDocs: Request failed - showing error message"
            );
            console.log("ProtectedDocs: Error message:", response.data.message);
            console.log("ProtectedDocs: Full error response:", response);

            showErrorMessage(
              response.data.message ||
                "Failed to submit request. Please try again."
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("ProtectedDocs AJAX Error:", {
            status: status,
            error: error,
            responseText: xhr.responseText,
          });
          showErrorMessage("An error occurred. Please try again.");
        },
        complete: function () {
          $submitBtn
            .prop("disabled", false)
            .removeClass("loading")
            .text(protecteddocs_frontend.submit_label || "Submit Request");
        },
      });
    }

    // Load more documents automatically
    function loadMoreDocumentsAuto(
      $grid,
      currentLimit,
      restriction,
      loadMoreLimit,
      $loadingIndicator,
      $container
    ) {
      // Show loading indicator
      $loadingIndicator.show();

      // Get featured image setting from container
      const featuredImage = $container.data("featured-image") || "yes";
      const colorPalette = $container.data("color-palette") || "default";

      $.ajax({
        url: protecteddocs_frontend.ajax_url,
        type: "POST",
        data: {
          action: "protecteddocs_load_more_documents",
          limit: currentLimit,
          restriction: restriction,
          load_more_limit: loadMoreLimit,
          featured_image: featuredImage,
          color_palette: colorPalette,
          nonce: protecteddocs_frontend.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Append new content to existing grid
            $grid.append(response.data.html);

            // Update container data with new limit
            $container.data("limit", response.data.current_limit);

            // Update pagination info
            $container
              .find(".authdocs-pagination-info")
              .text(
                `Showing 1-${response.data.current_limit} of ${response.data.total_documents} documents`
              );

            // Hide loading indicator if no more documents
            if (response.data.has_more === false) {
              $loadingIndicator.hide();
              $container
                .find(".authdocs-auto-pagination")
                .attr("data-auto-loading", "false");
            }
          } else {
            showErrorMessage(
              response.data.message || "Failed to load more documents."
            );
          }
        },
        error: function () {
          showErrorMessage("An error occurred while loading more documents.");
        },
        complete: function () {
          $loadingIndicator.hide();
        },
      });
    }

    // Load more documents
    function loadMoreDocuments(
      $grid,
      currentLimit,
      restriction,
      $btn,
      featuredImage = "yes",
      loadMoreLimit = null,
      colorPalette = "default"
    ) {
      const $loadMoreContainer = $btn.closest(".authdocs-load-more");
      // Use provided loadMoreLimit or fallback to data attribute
      if (!loadMoreLimit) {
        loadMoreLimit = parseInt($btn.data("load-more-limit")) || 12;
      }

      // Show loading state
      $btn
        .prop("disabled", true)
        .addClass("loading")
        .text(protecteddocs_frontend.loading_label || "Loading...");

      // Add loading class to grid and container
      $grid.addClass("loading");
      $grid.closest(".authdocs-grid-container").addClass("loading");

      $.ajax({
        url: protecteddocs_frontend.ajax_url,
        type: "POST",
        data: {
          action: "protecteddocs_load_more_documents",
          limit: currentLimit,
          restriction: restriction,
          load_more_limit: loadMoreLimit,
          featured_image: featuredImage,
          color_palette: colorPalette,
          nonce: protecteddocs_frontend.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Apply CSS if provided
            if (response.data.css) {
              applyDynamicCSS(response.data.css);
            }

            // Append new content to existing grid
            $grid.append(response.data.html);

            // Update button data and text
            $btn.data("current-limit", response.data.current_limit);

            // Hide load more button if no more documents
            if (response.data.has_more === false) {
              $loadMoreContainer.hide();
            }
          } else {
            showErrorMessage(
              response.data.message || "Failed to load more documents."
            );
          }
        },
        error: function () {
          showErrorMessage("An error occurred while loading documents.");
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .removeClass("loading")
            .text(
              protecteddocs_frontend.load_more_label || "Load More Documents"
            );

          // Remove loading class from grid and container
          $grid.removeClass("loading");
          $grid.closest(".authdocs-grid-container").removeClass("loading");
        },
      });
    }

    // Load page documents with pagination
    function loadPageDocuments($container, page) {
      const $grid = $container.find(".authdocs-grid");
      const $pagination = $container.find(".authdocs-pagination");
      const $paginationBtns = $container.find(".authdocs-pagination-btn");

      // Show loading state
      $paginationBtns.prop("disabled", true);
      $grid.addClass("loading");

      // Add loading class to container for CSS targeting
      $container.addClass("loading");

      // Get container data attributes
      const limit = getLimit($container, "limit");
      const restriction = $container.data("restriction") || "all";
      const orderby = $container.data("orderby") || "date";
      const order = $container.data("order") || "DESC";
      const featuredImage = $container.data("featured-image") || "yes";
      const paginationStyle = $container.data("pagination-style") || "classic";
      const paginationType = $container.data("pagination-type") || "ajax";
      const colorPalette = $container.data("color-palette") || "default";

      $.ajax({
        url: protecteddocs_frontend.ajax_url,
        type: "POST",
        data: {
          action: "protecteddocs_paginate_documents",
          page: page,
          limit: limit,
          restriction: restriction,
          orderby: orderby,
          order: order,
          featured_image: featuredImage,
          pagination_style: paginationStyle,
          pagination_type: paginationType,
          color_palette: colorPalette,
          nonce: protecteddocs_frontend.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Apply CSS if provided
            if (response.data.css) {
              applyDynamicCSS(response.data.css);
            }

            // Update grid content
            $grid.html(response.data.html);

            // Update pagination
            if (response.data.pagination_html) {
              $pagination.html(response.data.pagination_html);
            }

            // Update container data attributes
            $container.data("current-page", response.data.current_page);
            $container.data("total-pages", response.data.total_pages);
            $container.data("total-documents", response.data.total_documents);

            // Scroll to top of grid
            $("html, body").animate(
              {
                scrollTop: $container.offset().top - 100,
              },
              500
            );
          } else {
            showErrorMessage(
              response.data.message || "Failed to load documents."
            );
          }
        },
        error: function () {
          showErrorMessage("An error occurred while loading documents.");
        },
        complete: function () {
          $paginationBtns.prop("disabled", false);
          $grid.removeClass("loading");
          $container.removeClass("loading");
        },
      });
    }

    // Show success message
    function showSuccessMessage(message) {
      const messageHtml = `
            <div class="authdocs-notification authdocs-notification-success">
                <div class="authdocs-notification-content">
                    <div class="authdocs-notification-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </div>
                    <div class="authdocs-notification-text">
                        <div class="authdocs-notification-title">Success!</div>
                        <div class="authdocs-notification-message">${message}</div>
                    </div>
                    <button type="button" class="authdocs-notification-close" aria-label="Close notification">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;

      $("body").append(messageHtml);

      // Auto-hide after 6 seconds
      setTimeout(function () {
        $(".authdocs-notification-success").fadeOut(300, function () {
          $(this).remove();
        });
      }, 6000);

      // Handle close button
      $(".authdocs-notification-close").on("click", function () {
        $(this)
          .closest(".authdocs-notification")
          .fadeOut(300, function () {
            $(this).remove();
          });
      });
    }

    // Show error message
    function showErrorMessage(message) {
      const messageHtml = `
            <div class="authdocs-message authdocs-message-error">
                <span>${message}</span>
                <button type="button" class="authdocs-message-close">&times;</button>
            </div>
        `;

      $("body").append(messageHtml);

      // Auto-hide after 8 seconds
      setTimeout(function () {
        $(".authdocs-message-error").fadeOut(300, function () {
          $(this).remove();
        });
      }, 8000);

      // Handle close button
      $(".authdocs-message-close").on("click", function () {
        $(this)
          .closest(".authdocs-message")
          .fadeOut(300, function () {
            $(this).remove();
          });
      });
    }

    // Modern message styles are now handled in CSS file
    // No need for dynamic styles injection

    // Bot protection functions temporarily disabled for debugging
  }); // End document.ready
})(jQuery); // End IIFE

jQuery(document).ready(function ($) {
  "use strict";

  // Handle request access button clicks
  $(document).on("click", ".authdocs-request-access-btn", function (e) {
    e.preventDefault();
    const documentId = $(this).data("document-id");
    showRequestAccessModal(documentId);
  });

  // Handle load more button clicks
  $(document).on("click", ".authdocs-load-more-btn", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const currentLimit = parseInt($btn.data("current-limit"));
    const restriction = $btn.data("restriction");
    const $grid = $btn
      .closest(".authdocs-grid-load-more")
      .prev(".authdocs-grid");

    loadMoreDocuments($grid, currentLimit, restriction, $btn);
  });

  // Handle pagination button clicks
  $(document).on("click", ".authdocs-pagination-btn", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const page = parseInt($btn.data("page"));
    const $container = $btn.closest(".authdocs-grid-container");

    if (page && $container.length) {
      loadPageDocuments($container, page);
    }
  });

  // Show request access modal
  function showRequestAccessModal(documentId) {
    const modalHtml = `
            <div class="authdocs-modal" id="authdocs-request-modal">
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
                              authdocs_frontend.request_access_title ||
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
                                          authdocs_frontend.name_label ||
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
                                          authdocs_frontend.email_label ||
                                          "Email Address"
                                        }
                                    </label>
                                    <input type="email" id="authdocs-email" name="email" class="authdocs-form-input" placeholder="Enter your email address" required>
                                </div>
                                
                                <input type="hidden" name="document_id" value="${documentId}">
                                <input type="hidden" name="nonce" value="${
                                  authdocs_frontend.nonce
                                }">
                            </form>
                        </div>
                        
                        <div class="authdocs-modal-footer">
                            <button type="button" class="authdocs-btn authdocs-btn-outline authdocs-modal-close">
                                ${authdocs_frontend.cancel_label || "Cancel"}
                            </button>
                            <button type="button" class="authdocs-btn authdocs-btn-primary" id="authdocs-submit-request">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                                ${
                                  authdocs_frontend.submit_label ||
                                  "Submit Request"
                                }
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    $("body").append(modalHtml);
    $("#authdocs-request-modal").fadeIn(300);

    // Focus on first input
    $("#authdocs-name").focus();

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
    $("#authdocs-submit-request").on("click", function () {
      if (validateForm()) {
        submitAccessRequest();
      }
    });

    // Handle Enter key in form
    $("#authdocs-request-form").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        if (validateForm()) {
          submitAccessRequest();
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
    const formData = new FormData($form[0]);

    // Add action for AJAX
    formData.append("action", "authdocs_request_access");

    // Disable submit button and show loading
    $submitBtn
      .prop("disabled", true)
      .text(authdocs_frontend.submitting_label || "Submitting...");

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          showSuccessMessage(
            response.data.message || "Access request submitted successfully!"
          );
          closeRequestModal();
        } else {
          showErrorMessage(
            response.data.message ||
              "Failed to submit request. Please try again."
          );
        }
      },
      error: function (xhr, status, error) {
        showErrorMessage("An error occurred. Please try again.");
      },
      complete: function () {
        $submitBtn
          .prop("disabled", false)
          .text(authdocs_frontend.submit_label || "Submit Request");
      },
    });
  }

  // Load more documents
  function loadMoreDocuments($grid, currentLimit, restriction, $btn) {
    const $loadMoreContainer = $btn.closest(".authdocs-grid-load-more");
    const loadMoreLimit = parseInt($btn.data("load-more-limit")) || 12;

    // Show loading state
    $btn
      .prop("disabled", true)
      .text(authdocs_frontend.loading_label || "Loading...");

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: {
        action: "authdocs_load_more_documents",
        limit: currentLimit,
        restriction: restriction,
        load_more_limit: loadMoreLimit,
        nonce: authdocs_frontend.nonce,
      },
      success: function (response) {
        if (response.success) {
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
          .text(authdocs_frontend.load_more_label || "Load More Documents");
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

    // Get container data attributes
    const limit = parseInt($container.data("limit")) || 12;
    const restriction = $container.data("restriction") || "all";
    const orderby = $container.data("orderby") || "date";
    const order = $container.data("order") || "DESC";

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: {
        action: "authdocs_paginate_documents",
        page: page,
        limit: limit,
        restriction: restriction,
        orderby: orderby,
        order: order,
        nonce: authdocs_frontend.nonce,
      },
      success: function (response) {
        if (response.success) {
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
});

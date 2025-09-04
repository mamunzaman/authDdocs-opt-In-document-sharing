jQuery(document).ready(function ($) {
  "use strict";

  // Handle copy link button clicks
  $(document).on("click", ".authdocs-copy-link", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);
    var linkToCopy = $btn.data("link");

    // If data-link is not available, try to find the download link in the same row
    if (!linkToCopy) {
      var $downloadLink = $btn.closest("tr").find(".authdocs-download-link");
      if ($downloadLink.length > 0) {
        linkToCopy = $downloadLink.attr("href");
        console.log("AuthDocs: Found download link from href:", linkToCopy);
      }
    }

    console.log("AuthDocs: Copy button clicked");
    console.log("AuthDocs: Button element:", $btn);
    console.log("AuthDocs: Link to copy:", linkToCopy);

    if (!linkToCopy) {
      console.error("AuthDocs: No link data found for copy button");
      return;
    }

    // Use the Clipboard API if available
    if (navigator.clipboard && window.isSecureContext) {
      console.log("AuthDocs: Using Clipboard API to copy:", linkToCopy);
      navigator.clipboard
        .writeText(linkToCopy)
        .then(function () {
          console.log("AuthDocs: Clipboard API copy successful");
          showCopySuccess($btn);
        })
        .catch(function (err) {
          console.error("AuthDocs: Clipboard API failed, using fallback:", err);
          fallbackCopyTextToClipboard(linkToCopy, $btn);
        });
    } else {
      console.log("AuthDocs: Clipboard API not available, using fallback");
      // Fallback for older browsers or non-secure contexts
      fallbackCopyTextToClipboard(linkToCopy, $btn);
    }
  });

  // Fallback copy function for older browsers
  function fallbackCopyTextToClipboard(text, $btn) {
    console.log("AuthDocs: Using fallback copy method for text:", text);

    var textArea = document.createElement("textarea");
    textArea.value = text;

    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      var successful = document.execCommand("copy");
      console.log("AuthDocs: Fallback copy result:", successful);
      if (successful) {
        showCopySuccess($btn);
      } else {
        showCopyError($btn);
      }
    } catch (err) {
      console.error("AuthDocs: Fallback copy failed:", err);
      showCopyError($btn);
    }

    document.body.removeChild(textArea);
  }

  // Show copy success feedback
  function showCopySuccess($btn) {
    var $icon = $btn.find(".dashicons");
    var originalClass = $icon.attr("class");

    // Change icon to checkmark
    $icon.removeClass("dashicons-admin-page").addClass("dashicons-yes-alt");
    $btn.addClass("copied");

    // Show tooltip
    $btn.attr("title", "Link copied!");

    // Reset after 2 seconds
    setTimeout(function () {
      $icon.attr("class", originalClass);
      $btn.removeClass("copied");
      $btn.attr("title", "Copy link");
    }, 2000);
  }

  // Show copy error feedback
  function showCopyError($btn) {
    var $icon = $btn.find(".dashicons");
    var originalClass = $icon.attr("class");

    // Change icon to error
    $icon.removeClass("dashicons-admin-page").addClass("dashicons-warning");
    $btn.addClass("copy-error");

    // Show tooltip
    $btn.attr("title", "Copy failed");

    // Reset after 3 seconds
    setTimeout(function () {
      $icon.attr("class", originalClass);
      $btn.removeClass("copy-error");
      $btn.attr("title", "Copy link");
    }, 3000);
  }

  // Handle request management actions
  $(document).on("click", ".authdocs-action-link", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);

    console.log("AuthDocs: Action link clicked", $btn);

    // Prevent action if button is disabled
    if ($btn.hasClass("disabled") || $btn.prop("disabled")) {
      console.log("AuthDocs: Button is disabled, preventing action");
      return false;
    }

    var action = $btn.data("action");
    var requestId = $btn.data("request-id");
    var $row = $btn.closest("tr");

    console.log("AuthDocs: Action:", action, "Request ID:", requestId);

    var confirmMessage = "Are you sure you want to perform this action?";
    if (action === "decline") {
      confirmMessage =
        "Are you sure you want to revoke access? This will immediately invalidate any existing download links.";
    } else if (action === "inactive") {
      // Check current status to determine the action
      var $currentRow = $btn.closest("tr");
      var currentStatus = $currentRow
        .find("td[data-label='Status'] .authdocs-status")
        .text()
        .toLowerCase();

      if (currentStatus === "inactive") {
        confirmMessage =
          "Are you sure you want to activate this request? This will enable Accept/Decline options.";
      } else {
        confirmMessage =
          "Are you sure you want to deactivate this request? This will disable access and prevent further actions.";
      }
    }

    // Show confirmation popup instead of browser confirm
    console.log(
      "AuthDocs: Showing confirmation dialog with message:",
      confirmMessage
    );
    showConfirmDialog(confirmMessage, function () {
      // User confirmed, proceed with action
      console.log(
        "AuthDocs: User confirmed action, proceeding with:",
        action,
        requestId
      );
      proceedWithAction($btn, action, requestId);
    });
    return;
  });

  // WordPress native notice function
  function showNotice(message, type) {
    var noticeClass = "notice-info"; // Default to info

    if (type === "success") {
      noticeClass = "notice-success";
    } else if (type === "error") {
      noticeClass = "notice-error";
    } else if (type === "warning") {
      noticeClass = "notice-warning";
    }

    var $notice = $(
      '<div class="notice ' +
        noticeClass +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );

    // Remove any existing notices
    $(".notice").remove();

    // Add notice to the top of the page
    $(".wrap h1").after($notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      $notice.fadeOut();
    }, 5000);

    // Make dismissible
    $notice.on("click", ".notice-dismiss", function () {
      $notice.fadeOut();
    });
  }

  // WordPress native confirmation popup
  function showConfirmDialog(message, onConfirm) {
    console.log(
      "AuthDocs: Creating confirmation dialog with message:",
      message
    );

    // Remove any existing confirmation dialogs
    $(".authdocs-confirm-overlay").remove();

    var $overlay = $('<div class="authdocs-confirm-overlay"></div>');
    var $dialog = $(
      '<div class="authdocs-confirm-dialog">' +
        '<div class="authdocs-confirm-header">' +
        "<h3>Confirm Action</h3>" +
        "</div>" +
        '<div class="authdocs-confirm-body">' +
        "<p>" +
        message +
        "</p>" +
        "</div>" +
        '<div class="authdocs-confirm-footer">' +
        '<button type="button" class="button button-secondary authdocs-confirm-cancel">Cancel</button>' +
        '<button type="button" class="button button-primary authdocs-confirm-ok">OK</button>' +
        "</div>" +
        "</div>"
    );

    $overlay.append($dialog);
    $("body").append($overlay);

    console.log("AuthDocs: Confirmation dialog created and appended to body");

    // Trigger reflow and add show class for animation
    $overlay[0].offsetHeight;
    $overlay.addClass("show");

    // Handle button clicks using event delegation
    $overlay.on("click", ".authdocs-confirm-ok", function () {
      console.log("AuthDocs: Confirmation dialog OK clicked");
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
        onConfirm();
      }, 300);
    });

    $overlay.on("click", ".authdocs-confirm-cancel", function () {
      console.log("AuthDocs: Confirmation dialog Cancel clicked");
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
      }, 300);
    });

    // Close on overlay click
    $overlay.on("click", function (e) {
      if (e.target === this) {
        $overlay.removeClass("show");
        setTimeout(function () {
          $overlay.remove();
        }, 300);
      }
    });

    // Close on Escape key
    $(document).on("keydown.authdocs-confirm", function (e) {
      if (e.key === "Escape") {
        $overlay.removeClass("show");
        setTimeout(function () {
          $overlay.remove();
        }, 300);
        $(document).off("keydown.authdocs-confirm");
      }
    });
  }

  // Function to refresh row data after status change
  function refreshRowData($row, requestId) {
    console.log("refreshRowData called for request ID:", requestId);
    // Get the current row data and update it
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "authdocs_get_request_data",
        request_id: requestId,
        nonce: authdocs_admin.nonce,
      },
      success: function (response) {
        console.log("AJAX response received:", response);
        if (response.success && response.data) {
          var request = response.data;
          console.log("Request data:", request);

          // Update status column
          var $statusCell = $row.find("td[data-label='Status']");
          if ($statusCell.length) {
            var statusClass =
              "authdocs-status authdocs-status-" + request.status;
            $statusCell.html(
              '<span class="' +
                statusClass +
                '">' +
                request.status.charAt(0).toUpperCase() +
                request.status.slice(1) +
                "</span>"
            );
            console.log("Updated status cell");
          }

          // Update file link column
          var $fileLinkCell = $row.find("td[data-label='File Link']");
          if ($fileLinkCell.length && request.document_file) {
            if (request.status === "accepted" && request.secure_hash) {
              var downloadUrl =
                authdocs_admin.site_url +
                "?authdocs_download=" +
                request.document_id +
                "&hash=" +
                request.secure_hash +
                "&email=" +
                encodeURIComponent(request.requester_email) +
                "&request_id=" +
                request.id;
              $fileLinkCell.html(
                '<a href="' +
                  downloadUrl +
                  '" target="_blank" class="authdocs-download-link" title="Click to view document">' +
                  (request.document_file.filename ||
                    request.document_file.title) +
                  "</a><br>" +
                  '<div class="authdocs-link-container">' +
                  '<button type="button" class="authdocs-copy-link" title="Copy link" data-link="' +
                  downloadUrl +
                  '">' +
                  '<span class="dashicons dashicons-admin-page"></span>' +
                  "</button>" +
                  '<small class="authdocs-link-preview">' +
                  downloadUrl.substring(0, 50) +
                  "...</small></div>"
              );
              console.log("Updated file link cell with download link");
            } else {
              // Show appropriate status message based on request status
              var statusMessage = "";
              var linkClass = "authdocs-file-link";

              switch (request.status) {
                case "declined":
                  statusMessage = "Access declined";
                  linkClass = "authdocs-file-link authdocs-declined";
                  break;
                case "inactive":
                  statusMessage = "Request deactivated";
                  linkClass = "authdocs-file-link authdocs-inactive";
                  break;
                case "pending":
                  statusMessage = "Request pending approval";
                  break;
                case "accepted":
                  // This should not happen here as accepted status is handled above
                  statusMessage = "Access granted";
                  break;
                default:
                  statusMessage = "Request not accepted yet";
              }

              $fileLinkCell.html(
                '<a href="' +
                  request.document_file.url +
                  '" target="_blank" class="' +
                  linkClass +
                  '">' +
                  (request.document_file.filename ||
                    request.document_file.title) +
                  "</a><br>" +
                  '<small class="authdocs-status-note ' +
                  request.status +
                  '">' +
                  statusMessage +
                  "</small>"
              );
              console.log(
                "Updated file link cell with regular link - status:",
                request.status
              );
            }
          }

          // Update action buttons
          var $acceptBtn = $row.find(".authdocs-action-accept");
          var $declineBtn = $row.find(".authdocs-action-decline");
          var $inactiveBtn = $row.find(".authdocs-action-inactive");

          if ($acceptBtn.length) {
            var acceptText =
              request.status === "accepted" ? "Re-accept" : "Accept";
            $acceptBtn.attr("title", acceptText);
            console.log("Updated accept button tooltip");
          }

          if ($declineBtn.length) {
            var declineText =
              request.status === "declined" ? "Re-decline" : "Decline";
            $declineBtn.attr("title", declineText);
            console.log("Updated decline button tooltip");
          }

          if ($inactiveBtn.length) {
            var inactiveText =
              request.status === "inactive" ? "Activate" : "Deactivate";
            $inactiveBtn.attr("title", inactiveText);
            console.log("Updated inactive button tooltip");
          }

          // Enable/disable Accept and Decline buttons based on status
          if (request.status === "inactive") {
            $acceptBtn.addClass("disabled").prop("disabled", true);
            $declineBtn.addClass("disabled").prop("disabled", true);
            console.log(
              "Disabled Accept and Decline buttons for inactive status"
            );
          } else {
            // Enable buttons for pending, accepted, or declined status
            $acceptBtn.removeClass("disabled").prop("disabled", false);
            $declineBtn.removeClass("disabled").prop("disabled", false);
            console.log(
              "Enabled Accept and Decline buttons for status:",
              request.status
            );
          }

          // Remove loading class and re-enable button
          $row
            .find(".authdocs-action-link")
            .removeClass("loading")
            .prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", status, error);
        console.error("Response text:", xhr.responseText);
        // If refresh fails, just remove loading state
        $row
          .find(".authdocs-action-link")
          .removeClass("loading")
          .prop("disabled", false);
      },
    });
  }

  // Function to proceed with the action after confirmation
  function proceedWithAction($btn, action, requestId) {
    // Show loading state
    $btn.addClass("loading").prop("disabled", true);

    console.log("AuthDocs: Making AJAX request with data:", {
      action: "authdocs_manage_request",
      request_id: requestId,
      action_type: action,
      nonce: authdocs_admin.nonce,
    });

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "authdocs_manage_request",
        request_id: requestId,
        action_type: action,
        nonce: authdocs_admin.nonce,
      },
      success: function (response) {
        console.log("Main AJAX response:", response);
        if (response.success) {
          var $row = $btn.closest("tr");
          var $table = $row.closest("table");
          console.log("Row found:", $row.length > 0);

          // Get message from server response
          var actionMessage =
            response.data.message || "Request updated successfully.";
          var emailSent = response.data.email_sent || false;
          var newStatus = response.data.status || "";

          console.log("Action message:", actionMessage);
          console.log("Email sent:", emailSent);
          console.log("New status:", newStatus);

          // Determine if we should remove the row (for accept/decline actions)
          var shouldRemoveRow = false;
          if (action === "accept" || action === "decline") {
            shouldRemoveRow = true;
          }

          // Show success message with email status
          var messageType = emailSent ? "success" : "warning";
          if (
            !emailSent &&
            (newStatus === "accepted" || newStatus === "declined")
          ) {
            messageType = "warning";
          }

          showNotice(actionMessage, messageType);

          if (shouldRemoveRow) {
            // Add completed class for simple highlight
            $row.addClass("completed");

            // Remove highlight after 5 seconds with fade effect
            setTimeout(function () {
              $row.removeClass("completed fade-out");
            }, 5000);

            // Refresh the row data to show updated status and file link
            console.log(
              "About to call refreshRowData for request ID:",
              requestId
            );
            refreshRowData($row, requestId);
          } else {
            // For inactive status, refresh the row data to show updated status and disabled buttons
            refreshRowData($row, requestId);
          }
        } else {
          showNotice(
            "Error: " + (response.data || "Unknown error occurred"),
            "error"
          );
          $btn.removeClass("loading").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("AuthDocs: AJAX error:", status, error);
        console.error("AuthDocs: Response text:", xhr.responseText);
        showNotice("Network error. Please try again.", "error");
        $btn.removeClass("loading").prop("disabled", false);
      },
    });
  }

  // Handle bulk actions (if needed in future)
  $("#bulk-action-selector-top, #bulk-action-selector-bottom").on(
    "change",
    function () {
      var action = $(this).val();
      if (action && action !== "-1") {
        $("#doaction, #doaction2").prop("disabled", false);
      } else {
        $("#doaction, #doaction2").prop("disabled", true);
      }
    }
  );

  // Auto-refresh functionality removed - was causing page reloads every 30 seconds
  // If auto-refresh is needed in the future, implement it with AJAX instead of page reload

  // Handle document file upload in admin
  if ($("#authdocs_upload_button").length) {
    var file_frame;

    $("#authdocs_upload_button").on("click", function (e) {
      e.preventDefault();

      if (file_frame) {
        file_frame.open();
        return;
      }

      file_frame = wp.media.frames.file_frame = wp.media({
        title: "Select Document",
        button: {
          text: "Use this document",
        },
        multiple: false,
        library: {
          type: [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
          ],
        },
      });

      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $("#authdocs_file_id").val(attachment.id);
        $("#authdocs_file_preview").html(
          '<p><a href="' +
            attachment.url +
            '" target="_blank">' +
            attachment.filename +
            "</a></p>"
        );
      });

      file_frame.open();
    });
  }

  // Handle shortcode copy functionality
  $("input[readonly]").on("click", function () {
    $(this).select();
  });

  // Add tooltips for better UX
  $(".authdocs-action-btn").each(function () {
    var $btn = $(this);
    var action = $btn.data("action");
    var tooltip = "";

    switch (action) {
      case "accept":
        tooltip = "Approve this request and generate download link";
        break;
      case "decline":
        tooltip = "Reject this request";
        break;
      case "inactive":
        tooltip = "Deactivate this request";
        break;
    }

    if (tooltip) {
      $btn.attr("title", tooltip);
    }
  });

  // Handle responsive table improvements
  function handleResponsiveTable() {
    if ($(window).width() < 782) {
      $(".authdocs-requests-table-wrapper .wp-list-table").addClass(
        "mobile-view"
      );
    } else {
      $(".authdocs-requests-table-wrapper .wp-list-table").removeClass(
        "mobile-view"
      );
    }
  }

  $(window).on("resize", handleResponsiveTable);
  handleResponsiveTable();
});

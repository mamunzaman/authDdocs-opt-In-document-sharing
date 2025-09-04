jQuery(document).ready(function ($) {
  "use strict";

  // Handle request management actions
  $(".authdocs-action-link").on("click", function () {
    var $btn = $(this);

    // Prevent action if button is disabled
    if ($btn.hasClass("disabled") || $btn.prop("disabled")) {
      return false;
    }

    var action = $btn.data("action");
    var requestId = $btn.data("request-id");
    var $row = $btn.closest("tr");

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
    showConfirmDialog(confirmMessage, function () {
      // User confirmed, proceed with action
      proceedWithAction($btn, action, requestId);
    });
    return;
  });

  // WordPress native notice function
  function showNotice(message, type) {
    var noticeClass = type === "success" ? "notice-success" : "notice-error";
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

    // Trigger reflow and add show class for animation
    $overlay[0].offsetHeight;
    $overlay.addClass("show");

    // Handle button clicks
    $(".authdocs-confirm-ok").on("click", function () {
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
        onConfirm();
      }, 300);
    });

    $(".authdocs-confirm-cancel").on("click", function () {
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
                  request.document_file.filename +
                  "</a><br>" +
                  '<small class="authdocs-link-preview">' +
                  downloadUrl.substring(0, 50) +
                  "...</small>"
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
                  request.document_file.filename +
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
            $acceptBtn.find(".action-text").text(acceptText);
            console.log("Updated accept button text");
          }

          if ($declineBtn.length) {
            var declineText =
              request.status === "declined" ? "Re-decline" : "Decline";
            $declineBtn.find(".action-text").text(declineText);
            console.log("Updated decline button text");
          }

          if ($inactiveBtn.length) {
            var inactiveText =
              request.status === "inactive" ? "Activate" : "Deactivate";
            $inactiveBtn.find(".action-text").text(inactiveText);
            console.log("Updated inactive button text");
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

          // Determine action result message
          var actionMessage = "";
          var shouldRemoveRow = false;

          switch (action) {
            case "accept":
              actionMessage =
                "Request accepted successfully. Access granted email sent.";
              shouldRemoveRow = true;
              console.log("Action: accept - shouldRemoveRow:", shouldRemoveRow);
              break;
            case "reaccept":
              actionMessage =
                "Request re-accepted successfully. Access granted email sent.";
              shouldRemoveRow = true;
              console.log(
                "Action: reaccept - shouldRemoveRow:",
                shouldRemoveRow
              );
              break;
            case "decline":
              actionMessage = "Request declined successfully. Access revoked.";
              shouldRemoveRow = true;
              console.log(
                "Action: decline - shouldRemoveRow:",
                shouldRemoveRow
              );
              break;
            case "inactive":
              // Determine if we're activating or deactivating based on current status
              var $currentRow = $btn.closest("tr");
              var currentStatus = $currentRow
                .find("td[data-label='Status'] .authdocs-status")
                .text()
                .toLowerCase();

              if (currentStatus === "inactive") {
                actionMessage =
                  "Request activated successfully. Previous status restored.";
              } else {
                actionMessage =
                  "Request deactivated successfully. Access disabled.";
              }

              shouldRemoveRow = false; // Keep row but update status
              console.log(
                "Action: inactive - shouldRemoveRow:",
                shouldRemoveRow,
                "Current status:",
                currentStatus
              );
              break;
            default:
              actionMessage = "Request " + action + " successfully.";
              shouldRemoveRow = true;
              console.log(
                "Action: default - shouldRemoveRow:",
                shouldRemoveRow
              );
          }

          // Show success message
          showNotice(actionMessage, "success");

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
      error: function () {
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

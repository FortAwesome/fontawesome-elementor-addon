(function ($) {
  let pollTimer = null;
  const FAILED_MESSAGE = "💣 Failed";

  function setBusy(isBusy) {
    $("#fontawesome-elementor-addon-kit-setup-start").prop("disabled", isBusy);
    $("#fontawesome-elementor-addon-kit-setup-spinner").toggleClass(
      "is-active",
      isBusy,
    );
  }

  function displayErrors(errors) {
    if (!Array.isArray(errors)) {
      return;
    }

    for (const error of errors) {
      $("#fontawesome-elementor-errors-subsection").append(
        $("<p>").text(error.message),
      );
    }

    if (errors.length > 0) {
      $("#fontawesome-elementor-errors-subsection").show();
    }
  }

  function poll(buildId) {
    const delayMs = 1000;

    // Clear any existing timer before starting a new polling loop
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }

    function doPoll() {
      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_status",
        nonce: FontawesomeElementorAddonAdmin.nonce,
        build_id: buildId,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status").text(
              "Error checking status.",
            );
            setBusy(false);
            pollTimer = null;
            return;
          }

          const data = resp.data;
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            data.message || data.status,
          );

          if (data.done) {
            setBusy(false);
            $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
            $("#fontawesome-elementor-addon-kit-setup-status").text("✓");
            $("#fontawesome-elementor-addon-kit-setup-status").css(
              "color",
              "green",
            );
            const lastKitRefreshAtFormatted =
              data?.last_kit_refresh_at_formatted;

            if (lastKitRefreshAtFormatted) {
              $("#fontawesome-elementor-addon-last-kit-refresh-at").text(
                lastKitRefreshAtFormatted,
              );
            }

            pollTimer = null;
            return;
          }

          // Not done yet: wait, then poll again
          pollTimer = setTimeout(doPoll, delayMs);
        })
        .fail(function (resp) {
          $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
          $("#fontawesome-elementor-errors-subsection").show();
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            FAILED_MESSAGE,
          );
          const errors = resp?.responseJSON?.data || [];
          displayErrors(errors);
          setBusy(false);
          pollTimer = null;
        });
    }

    // Kick off the first poll immediately
    doPoll();
  }

  $(function () {
    $("#fontawesome-elementor-addon-kit-setup-start").on("click", function () {
      if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
      }

      setBusy(true);
      $("#fontawesome-elementor-addon-kit-setup-status").css("color", "unset");
      $("#fontawesome-elementor-addon-kit-setup-spinner").show();
      $("#fontawesome-elementor-addon-kit-setup-status").text("Starting…");

      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_start",
        nonce: FontawesomeElementorAddonAdmin.nonce,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status").text(
              FAILED_MESSAGE,
            );
            setBusy(false);
            return;
          }
          const buildId = resp.data.build_id;
          $("#fontawesome-elementor-addon-kit-setup-status").text("Running…");
          poll(buildId);
        })
        .fail(function (resp) {
          $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            FAILED_MESSAGE,
          );
          const errors = resp?.responseJSON?.data || [];
          displayErrors(errors);
          setBusy(false);
        });
    });
  });
})(jQuery);

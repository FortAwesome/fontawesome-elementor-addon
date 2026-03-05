(function ($) {
  let pollTimer = null;

  function setBusy(isBusy) {
    $("#fontawesome-elementor-addon-kit-setup-start").prop("disabled", isBusy);
    $("#fontawesome-elementor-addon-kit-setup-spinner").toggleClass(
      "is-active",
      isBusy,
    );
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
            $("#fontawesome-elementor-addon-kit-setup-status").text("Done.");
            const lastKitRefreshAtUnixEpochSeconds = data?.last_kit_refresh_at;

            if (
              lastKitRefreshAtUnixEpochSeconds &&
              Number.isInteger(lastKitRefreshAtUnixEpochSeconds)
            ) {
              const lastKitRefreshAt = new Date(
                lastKitRefreshAtUnixEpochSeconds * 1000,
              );
              $("#fontawesome-elementor-addon-last-kit-refresh-at").text(
                lastKitRefreshAt.toLocaleString(),
              );
            }

            pollTimer = null;
            return;
          }

          // Not done yet: wait, then poll again
          pollTimer = setTimeout(doPoll, delayMs);
        })
        .fail(function () {
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            "Request failed.",
          );
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
      $("#fontawesome-elementor-addon-kit-setup-status").text("Starting…");

      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_start",
        nonce: FontawesomeElementorAddonAdmin.nonce,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status").text(
              "Failed to start.",
            );
            setBusy(false);
            return;
          }
          const buildId = resp.data.build_id;
          $("#fontawesome-elementor-addon-kit-setup-status").text("Running…");
          poll(buildId);
        })
        .fail(function () {
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            "Start request failed.",
          );
          setBusy(false);
        });
    });
  });
})(jQuery);

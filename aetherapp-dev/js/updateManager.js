(function (global) {
  'use strict';

  if (global.AetherUpdateManager && global.AetherUpdateManager.started) {
    return;
  }

  const checkIntervalMilliseconds = 60 * 1000;
  const currentScriptUrl = document.currentScript && document.currentScript.src;
  const versionEndpoint = currentScriptUrl
    ? new URL('../version.php', currentScriptUrl).toString()
    : 'version.php';

  let loadedVersion = null;
  let checkInProgress = false;
  let timerId = null;
  let updateNotice = null;
  let hasUnsavedInput = false;

  function isNewVersion(currentVersion, availableVersion) {
    return typeof currentVersion === 'string'
      && currentVersion !== ''
      && typeof availableVersion === 'string'
      && availableVersion !== ''
      && currentVersion !== availableVersion;
  }

  function markInputAsUnsaved(event) {
    if (event.target && event.target.matches('input, textarea, select, [contenteditable="true"]')) {
      hasUnsavedInput = true;
    }
  }

  function showUpdateNotice(availableVersion) {
    if (updateNotice) {
      return;
    }

    const notice = document.createElement('div');
    notice.id = 'aether-update-notice';
    notice.setAttribute('role', 'status');
    notice.style.cssText = [
      'position:fixed',
      'right:1rem',
      'bottom:1rem',
      'z-index:1080',
      'max-width:26rem',
      'padding:1rem',
      'border:1px solid #0d6efd',
      'border-radius:.375rem',
      'background:#fff',
      'color:#212529',
      'box-shadow:0 .5rem 1rem rgba(0,0,0,.15)'
    ].join(';');

    const message = document.createElement('span');
    message.textContent = 'Er is een nieuwe versie van Aether beschikbaar. ';

    const refreshButton = document.createElement('button');
    refreshButton.type = 'button';
    refreshButton.className = 'btn btn-primary btn-sm';
    refreshButton.textContent = 'Vernieuwen';
    refreshButton.addEventListener('click', function () {
      if (hasUnsavedInput && !global.confirm('Er is mogelijk niet-opgeslagen invoer. Toch vernieuwen?')) {
        return;
      }

      global.location.reload();
    });

    notice.append(message, refreshButton);
    notice.dataset.availableVersion = availableVersion;
    document.body.appendChild(notice);
    updateNotice = notice;
  }

  async function checkForUpdate() {
    if (checkInProgress) {
      return;
    }

    checkInProgress = true;
    try {
      const response = await global.fetch(versionEndpoint, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });

      if (!response.ok) {
        return;
      }

      const payload = await response.json();
      const availableVersion = payload && typeof payload.version === 'string'
        ? payload.version.trim()
        : '';

      if (availableVersion === '') {
        return;
      }

      if (loadedVersion === null) {
        loadedVersion = availableVersion;
        return;
      }

      if (isNewVersion(loadedVersion, availableVersion)) {
        showUpdateNotice(availableVersion);
      }
    } catch (error) {
      // Een tijdelijke netwerkfout mag de gebruiker niet hinderen; de volgende timer probeert opnieuw.
    } finally {
      checkInProgress = false;
    }
  }

  function start() {
    document.addEventListener('input', markInputAsUnsaved, true);
    document.addEventListener('change', markInputAsUnsaved, true);
    checkForUpdate();
    timerId = global.setInterval(checkForUpdate, checkIntervalMilliseconds);
  }

  global.AetherUpdateManager = {
    started: true,
    checkForUpdate,
    isNewVersion,
    markChangesSaved: function () {
      hasUnsavedInput = false;
    },
    getTimerId: function () {
      return timerId;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
}(window));

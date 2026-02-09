(function () {
  function updateResults(htmlText) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(htmlText, 'text/html');
    var newResults = doc.querySelector('.aiscareer-results');
    var currentResults = document.querySelector('.aiscareer-results');
    if (newResults && currentResults) {
      currentResults.innerHTML = newResults.innerHTML;
    }
  }

  function ajaxSubmit(form) {
    if (!form) {
      return;
    }
    var formData = new FormData(form);
    var actionUrl = form.action;

    fetch(actionUrl, {
      method: 'POST',
      body: formData,
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) { return response.text(); })
      .then(function (htmlText) {
        updateResults(htmlText);
        highlightAvailableCountries();
      })
      .catch(function () {
        form.submit();
      });
  }

  function resetFilters(form) {
    if (!form) {
      return;
    }
    var selects = form.querySelectorAll('select');
    selects.forEach(function (el) {
      el.value = '';
    });
    var checks = form.querySelectorAll('input[type=\"checkbox\"], input[type=\"radio\"]');
    checks.forEach(function (el) {
      el.checked = false;
    });

    ajaxSubmit(form);

    var results = document.querySelector('.aiscareer-results');
    if (results && results.getAttribute('data-list-url')) {
      var cleanUrl = results.getAttribute('data-list-url');
      if (cleanUrl) {
        try {
          window.history.replaceState({}, '', cleanUrl);
        } catch (e) {
          // ignore history errors
        }
      }
    }
  }

  function bindFilterForm() {
    var form = document.querySelector('.aiscareer-filters form');
    if (!form) {
      return;
    }

    var resetButton = form.querySelector('.aiscareer-reset');
    if (resetButton) {
      resetButton.addEventListener('click', function (event) {
        event.preventDefault();
        resetFilters(form);
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      ajaxSubmit(form);
    });

    var inputs = form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]');
    inputs.forEach(function (el) {
      el.addEventListener('change', function () {
        ajaxSubmit(form);
      });
    });
  }

  function applyViewMode(view) {
    var results = document.querySelector('.aiscareer-results');
    if (!results) {
      return;
    }
    results.classList.toggle('is-list', view === 'list');
    results.classList.toggle('is-grid', view === 'grid');

    var buttons = document.querySelectorAll('.aiscareer-toggle-button');
    buttons.forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-view') === view);
    });
  }

  function bindViewToggle() {
    var buttons = document.querySelectorAll('.aiscareer-toggle-button');
    if (!buttons.length) {
      return;
    }
    var stored = null;
    try {
      stored = localStorage.getItem('aiscareerView');
    } catch (e) {
      stored = null;
    }
    var initial = stored === 'list' || stored === 'grid' ? stored : 'grid';
    applyViewMode(initial);

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.getAttribute('data-view');
        if (view !== 'list' && view !== 'grid') {
          return;
        }
        applyViewMode(view);
        try {
          localStorage.setItem('aiscareerView', view);
        } catch (e) {
          // ignore storage errors
        }
      });
    });
  }

  function bindMapControls() {
    var map = document.querySelector('.aiscareer-map');
    if (!map) {
      return;
    }
    var svgObject = map.querySelector('object[type="image/svg+xml"]');
    if (!svgObject) {
      return;
    }

    function getSvgRoot() {
      var doc = svgObject.contentDocument;
      if (!doc) {
        return null;
      }
      return doc.querySelector('svg');
    }

    var panX = 0;
    var panY = 0;
    var rafId = 0;
    var maxPan = 280;

    function applyTransform() {
      var svgRoot = getSvgRoot();
      if (!svgRoot) {
        return false;
      }
      var clampedX = Math.max(-maxPan, Math.min(maxPan, panX));
      var clampedY = Math.max(-maxPan, Math.min(maxPan, panY));
      panX = clampedX;
      panY = clampedY;
      svgRoot.style.transformOrigin = 'center center';
      svgRoot.style.transform = 'translate(' + panX + 'px, ' + panY + 'px) scale(' + zoom + ')';
      return true;
    }

    function scheduleTransform() {
      if (rafId) {
        return;
      }
      rafId = window.requestAnimationFrame(function () {
        rafId = 0;
        applyTransform();
      });
    }

    function withSvgReady(action) {
      var svgRoot = getSvgRoot();
      if (svgRoot) {
        action();
        return;
      }
      svgObject.addEventListener('load', function () {
        action();
      }, { once: true });
    }

    var zoom = 1;
    var minZoom = 0.8;
    var maxZoom = 2.5;
    var step = 0.2;

    var buttons = map.querySelectorAll('.aiscareer-map-btn');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-map-action');
        withSvgReady(function () {
          if (action === 'zoom-in') {
            zoom = Math.min(maxZoom, zoom + step);
          } else if (action === 'zoom-out') {
            zoom = Math.max(minZoom, zoom - step);
          } else {
            zoom = 1;
            panX = 0;
            panY = 0;
          }
          scheduleTransform();
        });
      });
    });

    function bindPan() {
      var svgRoot = getSvgRoot();
      if (!svgRoot) {
        return;
      }
      var dragging = false;
      var lastX = 0;
      var lastY = 0;
      var pointerId = null;
      svgRoot.__aiscareerMoved = false;

      svgRoot.style.cursor = 'grab';
      svgRoot.style.touchAction = 'none';

      svgRoot.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.buttons !== 1) {
          return;
        }
        dragging = true;
        svgRoot.__aiscareerMoved = false;
        pointerId = event.pointerId;
        lastX = event.clientX;
        lastY = event.clientY;
        svgRoot.setPointerCapture(pointerId);
        svgRoot.style.cursor = 'grabbing';
      });

      svgRoot.addEventListener('pointerup', function () {
        if (!dragging) {
          return;
        }
        dragging = false;
        pointerId = null;
        svgRoot.style.cursor = 'grab';
        svgRoot.__aiscareerMoved = false;
      });

      svgRoot.addEventListener('pointercancel', function () {
        if (!dragging) {
          return;
        }
        dragging = false;
        pointerId = null;
        svgRoot.style.cursor = 'grab';
        svgRoot.__aiscareerMoved = false;
      });

      svgRoot.addEventListener('pointermove', function (event) {
        if (!dragging) {
          return;
        }
        if (event.pointerType === 'mouse' && event.buttons !== 1) {
          dragging = false;
          pointerId = null;
          svgRoot.style.cursor = 'grab';
          return;
        }
        if (pointerId !== null && event.pointerId !== pointerId) {
          return;
        }
        var dx = event.clientX - lastX;
        var dy = event.clientY - lastY;
        lastX = event.clientX;
        lastY = event.clientY;
        if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
          svgRoot.__aiscareerMoved = true;
        }
        panX += dx;
        panY += dy;
        scheduleTransform();
      });
    }

    withSvgReady(function () {
      applyTransform();
      bindPan();
    });
  }

  // Fertige Lösung: verfügbare Länder im SVG markieren + beim Klick Country-Filter (Select) setzen
  function highlightAvailableCountries() {
    // Dropdown-Element holen (im Haupt-DOM)
    var countrySelect = document.getElementById('aiscareer-country');

    if (!countrySelect) {
      console.warn('Country dropdown not found');
      return;
    }

    // Alle verfügbaren Ländercodes aus dem Dropdown sammeln
    var availableCountries = [];
    var options = countrySelect.querySelectorAll('option');

    options.forEach(function (option) {
      var countryCode = option.value;
      // Leere / "All"-Option überspringen
      if (countryCode && countryCode !== '') {
        availableCountries.push(countryCode);
      }
    });

    // SVG-Object-Element finden
    var svgObject = document.querySelector('object[type="image/svg+xml"]');

    if (!svgObject) {
      console.warn('SVG object not found');
      return;
    }

    function scrollToFilters() {
      var filters = document.querySelector('.aiscareer-filters') || document.getElementById('aiscareer-country');
      if (filters && typeof filters.scrollIntoView === 'function') {
        filters.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    // Helper: Country Filter setzen (im Haupt-DOM)
    function setCountryFilter(countryCode) {
      var select = document.getElementById('aiscareer-country');
      if (!select) {
        return;
      }

      select.value = countryCode;

      // Change-Event triggern, damit vorhandene Listener/Filter-Logik feuert
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function resolveMapColors() {
      var root = document.querySelector('.aiscareer') || document.documentElement;
      var styles = getComputedStyle(root);
      var available = styles.getPropertyValue('--aiscareer-map-available').trim() || '#4caf50';
      var hover = styles.getPropertyValue('--aiscareer-map-hover').trim() || '#45a049';
      var active = styles.getPropertyValue('--aiscareer-map-active').trim() || '#2e7d32';
      return { available: available, hover: hover, active: active };
    }

    // Logik, die nach dem Laden des SVG ausgeführt wird
    function onSvgLoaded() {
      var svgDoc = svgObject.contentDocument;

      if (!svgDoc) {
        console.warn('Could not access SVG document');
        return;
      }

      var svgRoot = svgDoc.querySelector('svg');
      if (!svgRoot) {
        console.warn('No <svg> root found in SVG document');
        return;
      }

      // CSS-Styles direkt in das SVG-Dokument einfügen
      var styleElement = svgDoc.querySelector('#country-highlight-styles');
      if (!styleElement) {
        var colors = resolveMapColors();
        styleElement = svgDoc.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.id = 'country-highlight-styles';
        styleElement.textContent =
          '.available-country path { fill: ' + colors.available + ' !important; cursor: pointer; transition: fill 0.3s ease; }' +
          '.available-country:hover path { fill: ' + colors.hover + ' !important; }' +
          '.available-country.active path { fill: ' + colors.active + ' !important; }';
        svgRoot.appendChild(styleElement);
      }

      function setActiveCountry(countryCode) {
        var activeNodes = svgDoc.querySelectorAll('.available-country');
        activeNodes.forEach(function (el) {
          el.classList.remove('active');
        });
        if (!countryCode) {
          return;
        }
        var target = svgDoc.getElementById(countryCode);
        if (target) {
          target.classList.add('active');
        }
      }

      // Länder markieren
      var markedCount = 0;

      availableCountries.forEach(function (countryCode) {
        var svgElement = svgDoc.getElementById(countryCode);

        if (svgElement) {
          svgElement.classList.add('available-country');
          // Für den Fall, dass die ID direkt auf einem <path> liegt:
          if (svgElement.tagName && svgElement.tagName.toLowerCase() === 'path') {
            svgElement.style.cursor = 'pointer';
          }

          markedCount++;
        } else {
        }
      });

      // Auswahl aus Filter spiegeln
      setActiveCountry(countrySelect.value);
      if (!countrySelect.__aiscareerMapBound) {
        countrySelect.addEventListener('change', function () {
          setActiveCountry(countrySelect.value);
        });
        countrySelect.__aiscareerMapBound = true;
      }

      // Zentraler Click-Handler (robuster als pro Element)
      svgRoot.addEventListener('click', function (event) {
        if (svgRoot.__aiscareerMoved) {
          event.preventDefault();
          event.stopPropagation();
          svgRoot.__aiscareerMoved = false;
          return;
        }

        var hit = svgDoc.elementFromPoint(event.clientX, event.clientY);
        var node = hit || event.target;
        while (node && node !== svgRoot) {
          if (node.id && availableCountries.indexOf(node.id) !== -1) {
            setActiveCountry(node.id);
            setCountryFilter(node.id);
            scrollToFilters();
            return;
          }
          node = node.parentNode;
        }
      });
    }

    // Nur einmal binden (falls Funktion mehrfach läuft)
    svgObject.removeEventListener('load', onSvgLoaded);
    svgObject.addEventListener('load', onSvgLoaded);

    // Falls das Object bereits geladen ist, direkt ausführen
    if (svgObject.contentDocument && svgObject.contentDocument.querySelector('svg')) {
      onSvgLoaded();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bindFilterForm();
      bindViewToggle();
      bindMapControls();
      highlightAvailableCountries();
    });
  } else {
    bindFilterForm();
    bindViewToggle();
    bindMapControls();
    highlightAvailableCountries();
  }
})();

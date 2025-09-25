(function($) {
  'use strict';

  const settings = window.rbfBrandingSettings || {};
  const fontsCatalog = settings.fonts || {};
  const logoPlaceholderText = settings.logoPlaceholder || '';
  const brandPlaceholderText = settings.brandPlaceholder || '';
  const mediaTitle = settings.mediaTitle || 'Select a logo';
  const mediaButton = settings.mediaButton || 'Use logo';

  const previewEl = document.getElementById('rbf-brand-preview');
  if (!previewEl) {
    return;
  }

  const logoDisplayEl = previewEl.querySelector('.rbf-brand-preview__logo');
  const titleEl = previewEl.querySelector('.rbf-brand-preview__title');
  const ctaEl = previewEl.querySelector('.rbf-brand-preview__cta');

  const state = {
    accent: previewEl.getAttribute('data-accent') || '#000000',
    secondary: previewEl.getAttribute('data-secondary') || '#f8b500',
    radius: previewEl.getAttribute('data-radius') || '8px',
    fontBody: previewEl.getAttribute('data-font-body') || 'system',
    fontHeading: previewEl.getAttribute('data-font-heading') || 'system',
    logoUrl: previewEl.getAttribute('data-logo') || '',
    brandName: previewEl.getAttribute('data-brand-name') || ''
  };

  function sanitizeHex(value, fallback) {
    if (typeof value !== 'string') {
      return fallback;
    }

    const normalized = value.trim();
    if (!normalized) {
      return fallback;
    }

    const hasHash = normalized.charAt(0) === '#';
    const candidate = hasHash ? normalized : '#' + normalized;
    return /^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(candidate) ? candidate : fallback;
  }

  function resolveFontStack(fontKey, fallbackKey) {
    const normalized = fontKey && fontsCatalog[fontKey] ? fontKey : fallbackKey;
    const chosen = normalized && fontsCatalog[normalized] ? fontsCatalog[normalized] : fontsCatalog.system;

    return chosen && chosen.stack ? chosen.stack : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif";
  }

  function ensureFontLoaded(fontKey) {
    if (!fontKey || !fontsCatalog[fontKey] || !fontsCatalog[fontKey].google) {
      return;
    }

    if (document.querySelector('link[data-rbf-font="' + fontKey + '"]')) {
      return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=' + fontsCatalog[fontKey].google + '&display=swap';
    link.dataset.rbfFont = fontKey;
    document.head.appendChild(link);
  }

  function initialsFromBrand(name) {
    if (typeof name !== 'string' || !name.trim()) {
      return 'BR';
    }

    const parts = name.trim().split(/\s+/).slice(0, 2);
    const initials = parts.map((part) => part.charAt(0).toUpperCase()).join('');
    return initials || 'BR';
  }

  function updatePreviewLogo() {
    if (!logoDisplayEl) {
      return;
    }

    if (state.logoUrl) {
      logoDisplayEl.style.backgroundImage = 'url("' + state.logoUrl + '")';
      logoDisplayEl.style.backgroundSize = 'cover';
      logoDisplayEl.style.backgroundPosition = 'center';
      logoDisplayEl.textContent = '';
    } else {
      logoDisplayEl.style.backgroundImage = '';
      logoDisplayEl.textContent = initialsFromBrand(state.brandName);
    }
  }

  function updateTitle() {
    if (titleEl) {
      titleEl.textContent = state.brandName || brandPlaceholderText || '';
    }
  }

  function applyFonts() {
    ensureFontLoaded(state.fontBody);
    ensureFontLoaded(state.fontHeading);

    const bodyStack = resolveFontStack(state.fontBody, 'system');
    const headingStack = resolveFontStack(state.fontHeading, state.fontBody || 'system');

    previewEl.style.setProperty('--fppr-font-body', bodyStack);
    previewEl.style.setProperty('--fppr-font-heading', headingStack);
    previewEl.style.fontFamily = bodyStack;

    if (titleEl) {
      titleEl.style.fontFamily = headingStack;
    }
    if (ctaEl) {
      ctaEl.style.fontFamily = headingStack;
    }
    if (logoDisplayEl) {
      logoDisplayEl.style.fontFamily = headingStack;
    }
  }

  function applyColors() {
    const accent = sanitizeHex(state.accent, '#000000');
    const secondary = sanitizeHex(state.secondary, '#f8b500');
    const radius = typeof state.radius === 'string' && state.radius ? state.radius : '8px';

    previewEl.style.setProperty('--fppr-accent', accent);
    previewEl.style.setProperty('--fppr-secondary', secondary);
    previewEl.style.setProperty('--fppr-radius', radius);
  }

  function updateLogoFieldPreview() {
    const container = document.querySelector('.rbf-brand-logo-preview');
    if (!container) {
      return;
    }

    let img = container.querySelector('#rbf-brand-logo-preview-img');
    const placeholder = container.querySelector('#rbf-brand-logo-preview-placeholder');

    if (state.logoUrl) {
      if (!img) {
        img = document.createElement('img');
        img.id = 'rbf-brand-logo-preview-img';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        container.appendChild(img);
      }

      img.src = state.logoUrl;
      img.style.display = 'block';
      if (placeholder) {
        placeholder.style.display = 'none';
      }
    } else {
      if (img) {
        img.removeAttribute('src');
        img.style.display = 'none';
      }
      if (placeholder) {
        placeholder.style.display = 'block';
        placeholder.textContent = logoPlaceholderText;
      }
    }
  }

  function renderPreview() {
    applyColors();
    applyFonts();
    updateTitle();
    updatePreviewLogo();
  }

  function updateStateAndRender(partial) {
    Object.assign(state, partial);
    Object.keys(partial).forEach(function(key) {
      switch (key) {
        case 'accent':
          previewEl.setAttribute('data-accent', state.accent);
          break;
        case 'secondary':
          previewEl.setAttribute('data-secondary', state.secondary);
          break;
        case 'radius':
          previewEl.setAttribute('data-radius', state.radius);
          break;
        case 'fontBody':
          previewEl.setAttribute('data-font-body', state.fontBody);
          break;
        case 'fontHeading':
          previewEl.setAttribute('data-font-heading', state.fontHeading);
          break;
        case 'logoUrl':
          previewEl.setAttribute('data-logo', state.logoUrl);
          break;
        case 'brandName':
          previewEl.setAttribute('data-brand-name', state.brandName);
          break;
        default:
          break;
      }
    });
    renderPreview();
  }

  function bindColorInput(selector, key) {
    const input = $(selector);
    if (!input.length) {
      return;
    }

    input.on('input change', function() {
      updateStateAndRender({ [key]: $(this).val() });
    });
  }

  function bindSelect(selector, key) {
    const select = $(selector);
    if (!select.length) {
      return;
    }

    select.on('change', function() {
      const newValue = this.value || 'system';
      updateStateAndRender({ [key]: newValue });
    });
  }

  function bindText(selector, key) {
    const input = $(selector);
    if (!input.length) {
      return;
    }

    input.on('input', function() {
      updateStateAndRender({ [key]: $(this).val() });
    });
  }

  function bindLogoField() {
    const idField = $('#rbf_brand_logo_id');
    const urlField = $('#rbf_brand_logo_url');
    const selectButton = $('#rbf-brand-logo-select');
    const resetButton = $('#rbf-brand-logo-reset');

    if (urlField.length) {
      urlField.on('input change', function() {
        updateStateAndRender({ logoUrl: $(this).val() });
        updateLogoFieldPreview();
      });
    }

    if (resetButton.length) {
      resetButton.on('click', function(event) {
        event.preventDefault();
        if (idField.length) {
          idField.val('0');
        }
        if (urlField.length) {
          urlField.val('');
        }
        updateStateAndRender({ logoUrl: '' });
        updateLogoFieldPreview();
      });
    }

    if (!selectButton.length || typeof wp === 'undefined' || !wp.media) {
      return;
    }

    let mediaFrame = null;
    selectButton.on('click', function(event) {
      event.preventDefault();

      if (mediaFrame) {
        mediaFrame.open();
        return;
      }

      mediaFrame = wp.media({
        title: mediaTitle,
        button: {
          text: mediaButton
        },
        library: {
          type: ['image']
        },
        multiple: false
      });

      mediaFrame.on('select', function() {
        const attachment = mediaFrame.state().get('selection').first();
        if (!attachment) {
          return;
        }

        const data = attachment.toJSON();
        if (idField.length) {
          idField.val(data.id || 0);
        }
        if (urlField.length) {
          urlField.val(data.url || '');
        }

        updateStateAndRender({ logoUrl: data.url || '' });
        updateLogoFieldPreview();
      });

      mediaFrame.open();
    });
  }

  ensureFontLoaded(state.fontBody);
  ensureFontLoaded(state.fontHeading);
  renderPreview();
  updateLogoFieldPreview();

  bindColorInput('#rbf_accent_color', 'accent');
  bindColorInput('#rbf_secondary_color', 'secondary');
  bindSelect('#rbf_border_radius', 'radius');
  bindSelect('#rbf_brand_font_body', 'fontBody');
  bindSelect('#rbf_brand_font_heading', 'fontHeading');
  bindText('#rbf_brand_name', 'brandName');
  bindLogoField();
})(jQuery);

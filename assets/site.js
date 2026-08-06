(() => {
  if (!window.PhoneSeoul?.eventUrl) return;
  const storageKey = "specMatchAnonymousSession";
  let session = "";
  try {
    session = window.localStorage.getItem(storageKey) || "";
    if (!session) {
      session = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      window.localStorage.setItem(storageKey, session);
    }
  } catch {
    session = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  }

  const track = (postId, event) => {
    if (!postId) return;
    const payload = JSON.stringify({ post_id: Number(postId), event, session });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(PhoneSeoul.eventUrl, new Blob([payload], { type: "application/json" }));
    } else {
      fetch(PhoneSeoul.eventUrl, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload, keepalive: true }).catch(() => {});
    }
  };

  const current = document.querySelector("[data-current-product]");
  if (current) {
    try {
      const product = JSON.parse(current.dataset.currentProduct || "{}");
      window.setTimeout(() => track(product.id, "view"), 1200);
    } catch {}
  }

  document.addEventListener("click", (event) => {
    const target = event.target.closest("[data-track-event][data-track-post]");
    if (target) track(target.dataset.trackPost, target.dataset.trackEvent);
  });

  window.SpecMatchTrack = track;
})();

(() => {
  if (!window.PhoneSeoul?.searchUrl) return;

  const typeLabels = { phone: "스마트폰", laptop: "노트북", cpu: "CPU", gpu: "GPU" };
  document.querySelectorAll("[data-catalog-search]").forEach((form) => {
    const input = form.querySelector("[data-search-input]");
    const suggestions = form.querySelector("[data-search-suggestions]");
    if (!input || !suggestions) return;
    let timer = 0;
    let controller;

    const close = () => {
      suggestions.hidden = true;
      suggestions.replaceChildren();
    };

    input.addEventListener("input", () => {
      window.clearTimeout(timer);
      controller?.abort();
      const query = input.value.trim();
      if (query.length < 2) {
        close();
        return;
      }
      timer = window.setTimeout(async () => {
        controller = new AbortController();
        const selectedType = form.querySelector('[name="search_type"]:checked')?.value || "all";
        try {
          const response = await fetch(`${PhoneSeoul.searchUrl}?q=${encodeURIComponent(query)}&type=${encodeURIComponent(selectedType)}`, { signal: controller.signal });
          if (!response.ok) throw new Error("search failed");
          const items = await response.json();
          suggestions.replaceChildren();
          if (!items.length) {
            const empty = document.createElement("p");
            empty.textContent = "일치하는 제품이 없습니다.";
            suggestions.appendChild(empty);
          } else {
            items.slice(0, 8).forEach((item) => {
              const link = document.createElement("a");
              link.href = item.url;
              const meta = document.createElement("span");
              meta.textContent = `${typeLabels[item.type] || "제품"} · ${item.brand || "브랜드 미상"}`;
              const name = document.createElement("strong");
              name.textContent = item.name;
              link.append(meta, name);
              suggestions.appendChild(link);
            });
          }
          suggestions.hidden = false;
        } catch (error) {
          if (error.name !== "AbortError") close();
        }
      }, 180);
    });

    form.addEventListener("submit", (event) => {
      if (!input.value.trim()) event.preventDefault();
    });
    form.querySelectorAll('[name="search_type"]').forEach((control) => {
      control.addEventListener("change", () => form.requestSubmit());
    });
    input.addEventListener("keydown", (event) => {
      if (event.key === "Escape") close();
    });
    document.addEventListener("click", (event) => {
      if (!form.contains(event.target)) close();
    });
  });

  const toggle = document.querySelector("[data-header-search-toggle]");
  const panel = document.querySelector("[data-header-search-panel]");
  toggle?.addEventListener("click", () => {
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute("aria-expanded", String(open));
    if (open) panel.querySelector("[data-search-input]")?.focus();
  });
})();

(() => {
  const builder = document.querySelector("[data-compare-builder]");
  if (!builder || !window.PhoneSeoul) return;

  const pickers = [...builder.querySelectorAll("[data-picker]")];
  const submit = builder.querySelector("[data-compare-submit]");
  const keywordBar = document.querySelector("[data-compare-keywords]");
  const categoryBar = document.querySelector("[data-compare-categories]");
  let requestId = 0;
  let activePicker = pickers[0];
  const initialParams = new URLSearchParams(window.location.search);
  const initialSlug = initialParams.get("phone");
  const initialName = initialParams.get("name");
  const initialPostId = initialParams.get("post_id");
  const keywordsByType = {
    phone: ["Samsung", "Apple", "Google", "Xiaomi", "Huawei", "LG"],
    laptop: ["Apple", "Samsung", "Lenovo", "Dell", "HP", "Asus"],
    cpu: ["Intel", "AMD", "Apple", "Snapdragon"],
    gpu: ["Nvidia", "AMD", "Intel", "Apple"],
  };

  const updateKeywords = (type) => {
    if (!keywordBar) return;
    keywordBar.querySelectorAll("[data-keyword]").forEach((item) => item.remove());
    (keywordsByType[type] || keywordsByType.phone).forEach((keyword) => {
      const button = document.createElement("button");
      button.type = "button";
      button.dataset.keyword = keyword;
      button.textContent = keyword;
      keywordBar.appendChild(button);
    });
  };

  const updateButton = () => {
    submit.disabled = !pickers.every((picker) =>
      picker.querySelector("[data-selected-slug]").value
    );
  };

  pickers.forEach((picker) => {
    const input = picker.querySelector('input[type="search"]');
    const selected = picker.querySelector("[data-selected-slug]");
    const results = picker.querySelector("[data-results]");
    let timer;

    input.addEventListener("focus", () => {
      activePicker = picker;
    });

    input.addEventListener("input", () => {
      selected.value = "";
      updateButton();
      window.clearTimeout(timer);
      const query = input.value.trim();
      if (query.length < 2) {
        results.hidden = true;
        return;
      }
      timer = window.setTimeout(async () => {
        const currentRequest = ++requestId;
        results.hidden = false;
        results.innerHTML = "<p>검색 중…</p>";
        try {
          const response = await fetch(
            `${PhoneSeoul.searchUrl}?q=${encodeURIComponent(query)}&type=${encodeURIComponent(builder.dataset.currentType || "phone")}`
          );
          const items = await response.json();
          if (currentRequest !== requestId) return;
          results.innerHTML = "";
          if (!items.length) {
            results.innerHTML = "<p>검색 결과가 없습니다.</p>";
            return;
          }
          items.forEach((item) => {
            const button = document.createElement("button");
            button.type = "button";
            button.innerHTML = `<strong>${item.name}</strong><span>${item.brand || ""}</span>`;
            button.addEventListener("click", () => {
              input.value = item.name;
              selected.value = item.slug;
              selected.dataset.postId = item.id;
              results.hidden = true;
              updateButton();
            });
            results.appendChild(button);
          });
        } catch {
          results.innerHTML = "<p>검색에 실패했습니다.</p>";
        }
      }, 250);
    });
  });

  if (initialSlug && pickers[0]) {
    pickers[0].querySelector("[data-selected-slug]").value = initialSlug;
    if (initialPostId) pickers[0].querySelector("[data-selected-slug]").dataset.postId = initialPostId;
    pickers[0].querySelector('input[type="search"]').value = initialName || initialSlug;
    activePicker = pickers[1] || pickers[0];
    updateButton();
    activePicker.querySelector('input[type="search"]')?.focus();
  }

  keywordBar?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-keyword]");
    if (!button) return;
    const target =
      pickers.find((picker) => !picker.querySelector("[data-selected-slug]").value) ||
      activePicker;
    const input = target.querySelector('input[type="search"]');
    activePicker = target;
    input.value = button.dataset.keyword;
    input.focus();
    input.dispatchEvent(new Event("input", { bubbles: true }));
  });

  categoryBar?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-compare-type]");
    if (!button) return;
    builder.dataset.currentType = button.dataset.compareType || "phone";
    updateKeywords(builder.dataset.currentType);
    categoryBar.querySelectorAll("[data-compare-type]").forEach((item) => {
      item.classList.toggle("is-active", item === button);
    });
    pickers.forEach((picker) => {
      picker.querySelector('input[type="search"]').value = "";
      picker.querySelector("[data-selected-slug]").value = "";
      picker.querySelector("[data-results]").hidden = true;
    });
    submit.disabled = true;
    pickers[0].querySelector('input[type="search"]')?.focus();
  });

  submit.addEventListener("click", () => {
    const [a, b] = pickers.map(
      (picker) => picker.querySelector("[data-selected-slug]").value
    );
    if (!a || !b || a === b) return;
    pickers.forEach((picker) => window.SpecMatchTrack?.(picker.querySelector("[data-selected-slug]").dataset.postId, "compare"));
    const type = builder.dataset.currentType || "phone";
    const typePath = type === "phone" ? "" : `${type}/`;
    window.location.href = `${PhoneSeoul.compareBase}${typePath}${a}-vs-${b}/`;
  });
})();

(() => {
  const section = document.querySelector("[data-recent-products]");
  if (!section) return;

  let current;
  try {
    current = JSON.parse(section.dataset.currentProduct || "{}");
  } catch {
    return;
  }
  if (!current.id || !current.url) return;

  const storageKey = "specMatchRecentProducts";
  let stored = [];
  try {
    stored = JSON.parse(window.localStorage.getItem(storageKey) || "[]");
    if (!Array.isArray(stored)) stored = [];
  } catch {
    stored = [];
  }

  const previous = stored.filter((item) => item && item.id !== current.id).slice(0, 4);
  const list = section.querySelector("[data-recent-list]");
  previous.forEach((item) => {
    const article = document.createElement("article");
    const link = document.createElement("a");
    link.href = item.url;

    if (item.image) {
      const image = document.createElement("img");
      image.src = item.image;
      image.alt = "";
      image.loading = "lazy";
      image.referrerPolicy = "no-referrer";
      link.appendChild(image);
    }

    const copy = document.createElement("span");
    const meta = document.createElement("small");
    const name = document.createElement("strong");
    meta.textContent = [item.brand, item.type?.toUpperCase()].filter(Boolean).join(" / ");
    name.textContent = item.name || "";
    copy.append(meta, name);
    link.appendChild(copy);
    article.appendChild(link);
    list.appendChild(article);
  });

  if (previous.length) section.hidden = false;
  try {
    window.localStorage.setItem(storageKey, JSON.stringify([current, ...previous].slice(0, 8)));
  } catch {
    // Storage can be unavailable in private browsing modes.
  }
})();

(() => {
  const filters = document.querySelector("[data-compare-filters]");
  const table = document.querySelector("[data-compare-table]");
  if (!filters || !table) return;

  filters.addEventListener("click", (event) => {
    const button = event.target.closest("[data-mode]");
    if (!button) return;
    const showAll = button.dataset.mode === "all";
    filters.querySelectorAll("[data-mode]").forEach((item) => {
      item.classList.toggle("is-active", item === button);
    });
    table.querySelectorAll(".compare-row.is-same").forEach((row) => {
      row.hidden = !showAll;
    });
  });
})();

(() => {
  document.querySelectorAll("[data-catalog-view-switcher]").forEach((switcher) => {
    const target = document.querySelector("[data-catalog-view]");
    const button = switcher.querySelector("[data-catalog-view-toggle]");
    if (!target || !button) return;

    const storageKey = `specmatch-catalog-view-${switcher.dataset.viewKey || "catalog"}`;
    const setView = (mode) => {
      const compact = mode === "compact";
      target.classList.toggle("is-compact", compact);
      button.classList.toggle("is-compact", compact);
      const actionLabel = compact ? "썸네일 보기로 전환" : "간략 보기로 전환";
      button.setAttribute("aria-label", actionLabel);
      button.setAttribute("title", actionLabel);
      try {
        localStorage.setItem(storageKey, mode);
      } catch (error) {
        // The control still works when browser storage is unavailable.
      }
    };

    let initialView = "grid";
    try {
      initialView = localStorage.getItem(storageKey) === "compact" ? "compact" : "grid";
    } catch (error) {
      initialView = "grid";
    }
    setView(initialView);

    button.addEventListener("click", () => {
      setView(target.classList.contains("is-compact") ? "grid" : "compact");
    });
  });
})();

(() => {
  document.querySelectorAll("[data-brand-filter]").forEach((filter) => {
    const toggle = filter.querySelector("[data-brand-filter-toggle]");
    const options = filter.querySelector("[data-brand-filter-options]");
    if (!toggle || !options) return;

    const setOpen = (open) => {
      toggle.setAttribute("aria-expanded", String(open));
      options.classList.toggle("is-open", open);
    };

    toggle.addEventListener("click", () => {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
        setOpen(false);
        toggle.focus();
      }
    });
  });
})();

(() => {
  document.querySelectorAll("[data-catalog-tools]").forEach((tools) => {
    const toggle = tools.querySelector("[data-catalog-tools-toggle]");
    const panel = tools.querySelector("[data-catalog-tools-panel]");
    if (!toggle || !panel) return;

    const setOpen = (open) => {
      toggle.setAttribute("aria-expanded", String(open));
      panel.classList.toggle("is-open", open);
    };

    toggle.addEventListener("click", () => {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
        setOpen(false);
        toggle.focus();
      }
    });
  });
})();

(() => {
  const button = document.querySelector("[data-menu-toggle]");
  const menu = document.querySelector("[data-mobile-menu]");
  const brandButtons = [...document.querySelectorAll("[data-brand-toggle]")];
  if (!button || !menu) return;

  const closeBrands = (except = null) => {
    brandButtons.forEach((brandButton) => {
      if (brandButton === except) return;
      const brandMenu = document.getElementById(brandButton.getAttribute("aria-controls"));
      brandButton.setAttribute("aria-expanded", "false");
      brandButton.setAttribute("aria-label", `${brandButton.dataset.label} 브랜드 메뉴 열기`);
      brandMenu?.classList.remove("is-open");
    });
  };

  const closeMenu = () => {
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-label", "메뉴 열기");
    menu.classList.remove("is-open");
    document.documentElement.classList.remove("mobile-menu-open");
    closeBrands();
  };

  button.addEventListener("click", () => {
    const open = button.getAttribute("aria-expanded") !== "true";
    button.setAttribute("aria-expanded", String(open));
    button.setAttribute("aria-label", open ? "메뉴 닫기" : "메뉴 열기");
    menu.classList.toggle("is-open", open);
    document.documentElement.classList.toggle("mobile-menu-open", open);
  });

  menu.addEventListener("click", (event) => {
    if (event.target.closest("a") && !event.target.closest(".site-nav__catalog-trigger")) closeMenu();
  });

  brandButtons.forEach((brandButton) => {
    const brandMenu = document.getElementById(brandButton.getAttribute("aria-controls"));
    if (!brandMenu) return;
    brandButton.addEventListener("click", (event) => {
      event.stopPropagation();
      const open = brandButton.getAttribute("aria-expanded") !== "true";
      closeBrands(brandButton);
      brandButton.setAttribute("aria-expanded", String(open));
      brandButton.setAttribute("aria-label", `${brandButton.dataset.label} 브랜드 메뉴 ${open ? "닫기" : "열기"}`);
      brandMenu.classList.toggle("is-open", open);
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
      button.focus();
    }
  });

  document.addEventListener("click", (event) => {
    if (!menu.contains(event.target) && !button.contains(event.target)) closeMenu();
    if (!event.target.closest("[data-brand-menu]") && !event.target.closest("[data-brand-toggle]")) closeBrands();
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 900) closeMenu();
  });
})();

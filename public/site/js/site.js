// Small enhancements. Every page works without JavaScript; this adds the light/dark toggle,
// copy buttons on code, the back-to-top button, and math rendering (Temml, loaded only when needed).
// Labels come from data attributes on <body>, rendered from lang/{fa,en}/site.php.
(function () {
  "use strict";
  var root = document.documentElement;
  var body = document.body;
  var labels = { copy: body.dataset.labelCopy || "", copied: body.dataset.labelCopied || "" };
  var announcer = document.getElementById("announcer");

  // Screen readers hear "Copied" through a polite live region.
  function announce(text) {
    if (!announcer) return;
    announcer.textContent = "";
    setTimeout(function () { announcer.textContent = text; }, 50);
  }

  function copy(text, button, onDone) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(text).then(function () {
      button.dataset.copied = "1";
      announce(labels.copied);
      if (onDone) onDone();
      setTimeout(function () { delete button.dataset.copied; }, 1500);
    });
  }

  document.querySelectorAll(".theme-toggle").forEach(function (btn) {
    btn.setAttribute("aria-pressed", root.dataset.theme === "dark" ? "true" : "false");
    btn.addEventListener("click", function () {
      var next = root.dataset.theme === "dark" ? "light" : "dark";
      root.dataset.theme = next;
      btn.setAttribute("aria-pressed", next === "dark" ? "true" : "false");
      try { localStorage.setItem("theme", next); } catch (e) { /* storage blocked */ }
    });
  });

  // Copy buttons on code blocks.
  document.querySelectorAll(".post-content pre").forEach(function (pre) {
    var wrap = document.createElement("div");
    wrap.className = "code-block";
    pre.parentNode.insertBefore(wrap, pre);
    wrap.appendChild(pre);
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "copy-code";
    btn.textContent = labels.copy;
    btn.addEventListener("click", function () {
      copy(pre.innerText, btn, function () {
        btn.textContent = labels.copied;
        setTimeout(function () { btn.textContent = labels.copy; }, 1500);
      });
    });
    wrap.appendChild(btn);
  });

  document.querySelectorAll(".copy-link").forEach(function (btn) {
    btn.addEventListener("click", function () { copy(btn.dataset.url, btn); });
  });

  window.addEventListener("scroll", function () {
    root.classList.toggle("scrolled", window.scrollY > 600);
  }, { passive: true });

  // Math: $…$ and $$…$$ arrive as TeX text and are turned into MathML in the browser.
  var math = document.querySelectorAll(".math");
  if (math.length) {
    if (body.dataset.temmlCss) {
      var css = document.createElement("link");
      css.rel = "stylesheet";
      css.href = body.dataset.temmlCss;
      document.head.appendChild(css);
    }
    var script = document.createElement("script");
    script.src = body.dataset.temml;
    script.onload = function () {
      math.forEach(function (el) {
        try {
          window.temml.render(el.textContent, el, { displayMode: el.classList.contains("math-block"), throwOnError: false });
          el.classList.add("math-rendered");
        } catch (e) { /* leave the TeX visible */ }
      });
    };
    document.head.appendChild(script);
  }
})();

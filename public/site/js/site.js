// Small enhancements. Every page works without JavaScript; this adds the light/dark toggle,
// copy buttons on code, the back-to-top button, and math rendering (Temml, loaded only when needed).
(function () {
  "use strict";
  var root = document.documentElement;
  var fa = root.lang === "fa";

  document.querySelectorAll(".theme-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var next = root.dataset.theme === "dark" ? "light" : "dark";
      root.dataset.theme = next;
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
    btn.textContent = fa ? "کپی" : "Copy";
    btn.addEventListener("click", function () {
      if (!navigator.clipboard) return;
      navigator.clipboard.writeText(pre.innerText).then(function () {
        btn.textContent = fa ? "کپی شد" : "Copied";
        setTimeout(function () { btn.textContent = fa ? "کپی" : "Copy"; }, 1500);
      });
    });
    wrap.appendChild(btn);
  });

  document.querySelectorAll(".copy-link").forEach(function (btn) {
    btn.addEventListener("click", function () {
      if (navigator.clipboard) navigator.clipboard.writeText(btn.dataset.url);
      btn.dataset.copied = "1";
      setTimeout(function () { delete btn.dataset.copied; }, 1500);
    });
  });

  window.addEventListener("scroll", function () {
    root.classList.toggle("scrolled", window.scrollY > 600);
  }, { passive: true });

  // Math: $…$ and $$…$$ arrive as TeX text and are turned into MathML in the browser.
  var math = document.querySelectorAll(".math");
  if (math.length) {
    var src = document.body.dataset.temml;
    var script = document.createElement("script");
    script.src = src;
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

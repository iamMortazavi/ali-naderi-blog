// Small enhancements. The site works without JavaScript; this adds the theme toggle,
// copy buttons, the back-to-top button and search.

const root = document.documentElement;

// Light / dark toggle (the initial value is set inline in <head> to avoid a flash).
document.querySelectorAll(".theme-toggle").forEach((btn) => {
  btn.addEventListener("click", () => {
    const next = root.dataset.theme === "dark" ? "light" : "dark";
    root.dataset.theme = next;
    try { localStorage.setItem("theme", next); } catch (e) { /* storage blocked */ }
  });
});

const copy = (text, el, done) => {
  if (!navigator.clipboard) return;
  navigator.clipboard.writeText(text).then(() => {
    const old = el.getAttribute("aria-label");
    el.setAttribute("aria-label", done);
    el.dataset.copied = "1";
    setTimeout(() => { el.setAttribute("aria-label", old); delete el.dataset.copied; }, 1500);
  });
};

// Copy buttons on code blocks.
const isFa = root.lang === "fa";
document.querySelectorAll(".post-content .highlight").forEach((block) => {
  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "copy-code";
  btn.textContent = isFa ? "کپی" : "Copy";
  btn.addEventListener("click", () => {
    const code = block.querySelector("code");
    navigator.clipboard && navigator.clipboard.writeText(code ? code.innerText : "").then(() => {
      btn.textContent = isFa ? "کپی شد" : "Copied";
      setTimeout(() => { btn.textContent = isFa ? "کپی" : "Copy"; }, 1500);
    });
  });
  block.appendChild(btn);
});

document.querySelectorAll(".copy-link").forEach((btn) => {
  btn.addEventListener("click", () => copy(btn.dataset.url, btn, btn.dataset.done));
});

// Show the back-to-top button after scrolling.
const onScroll = () => root.classList.toggle("scrolled", window.scrollY > 600);
window.addEventListener("scroll", onScroll, { passive: true });

// Search: a plain substring search over a small JSON index, with Persian letter normalisation.
const input = document.getElementById("q");
if (input) {
  const results = document.getElementById("search-results");
  const normalize = (s) => (s || "")
    .toLowerCase()
    .replace(/[يى]/g, "ی")
    .replace(/ك/g, "ک")
    .replace(/[ة]/g, "ه")
    .replace(/[أإآ]/g, "ا")
    .replace(/[ً-ٰٟ]/g, "") // Arabic diacritics
    .replace(/[‌‏‎]/g, " ") // half-space and direction marks
    .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0))
    .replace(/\s+/g, " ")
    .trim();
  const escape = (s) => s.replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));

  let index = null;
  const load = () => index || (index = fetch(input.dataset.index)
    .then((r) => r.json())
    .then((items) => items.map((it) => ({
      ...it,
      nt: normalize(it.t),
      nx: normalize([it.d, (it.g || []).join(" "), it.c].join(" ")),
    }))));

  const render = (q) => {
    const words = normalize(q).split(" ").filter(Boolean);
    if (!words.length) { results.innerHTML = ""; return; }
    results.innerHTML = `<p class="search-hint">${escape(input.dataset.loading)}</p>`;
    load().then((items) => {
      const hits = items
        .map((it) => {
          let score = 0;
          for (const w of words) {
            const inTitle = it.nt.includes(w);
            if (!inTitle && !it.nx.includes(w)) return null;
            score += inTitle ? 10 : 1;
          }
          return { it, score };
        })
        .filter(Boolean)
        .sort((a, b) => b.score - a.score)
        .slice(0, 30);
      results.innerHTML = hits.length
        ? hits.map(({ it }) => `<article class="post-card"><h2 class="post-card-title"><a href="${escape(it.u)}">${escape(it.t)}</a></h2><p class="post-card-summary">${escape(it.d)}</p><footer class="post-meta">${escape(it.a)}</footer></article>`).join("")
        : `<p class="search-hint">${escape(input.dataset.empty)}</p>`;
    });
  };

  let timer;
  input.addEventListener("input", () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      render(input.value);
      const url = new URL(location.href);
      input.value ? url.searchParams.set("q", input.value) : url.searchParams.delete("q");
      history.replaceState(null, "", url);
    }, 150);
  });
  const initial = new URLSearchParams(location.search).get("q");
  if (initial) { input.value = initial; render(initial); }
  input.addEventListener("focus", load, { once: true });
}

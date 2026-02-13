(function () {
  function showToast(msg) {
    var t = document.createElement("div");
    t.textContent = msg;

    t.style.position = "fixed";
    t.style.left = "50%";
    t.style.top = "14px";
    t.style.transform = "translateX(-50%)";
    t.style.zIndex = "9999";
    t.style.padding = "10px 14px";
    t.style.borderRadius = "999px";
    t.style.border = "1px solid #e5e7eb";
    t.style.background = "rgba(17,24,39,.92)";
    t.style.color = "#fff";
    t.style.boxShadow = "0 10px 24px rgba(0,0,0,.18)";
    t.style.fontSize = "14px";
    t.style.opacity = "0";
    t.style.transition = "opacity .18s ease, transform .18s ease";
    t.style.pointerEvents = "none";

    document.body.appendChild(t);

    // ふわっと出る
    requestAnimationFrame(function () {
      t.style.opacity = "1";
      t.style.transform = "translateX(-50%) translateY(2px)";
    });

    // 1.4秒後に消える
    setTimeout(function () {
      t.style.opacity = "0";
      t.style.transform = "translateX(-50%) translateY(-4px)";
      setTimeout(function () {
        if (t && t.parentNode) t.parentNode.removeChild(t);
      }, 250);
    }, 1400);
  }

  // URLに ?toast=... があれば表示（安全にデコード）
  var params = new URLSearchParams(window.location.search);
  var toast = params.get("toast");
  if (toast) {
    try {
      showToast(decodeURIComponent(toast));
    } catch (e) {
      showToast(toast);
    }
  }
})();

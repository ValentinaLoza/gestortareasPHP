(async function () {
  "use strict";

  const campo = document.getElementById("gestorUrl");
  const estado = document.getElementById("estado");
  const guardado = await chrome.storage.local.get("gestorUrl");

  if (guardado.gestorUrl) {
    campo.value = guardado.gestorUrl;
  }

  document.getElementById("guardar").addEventListener("click", async () => {
    const valor = campo.value.trim().replace(/\/$/, "");

    if (!valor.startsWith("https://")) {
      estado.textContent = "La dirección debe comenzar con https://";
      return;
    }

    await chrome.storage.local.set({ gestorUrl: valor });
    estado.textContent = "Configuración guardada.";

    setTimeout(() => {
      estado.textContent = "";
    }, 2500);
  });
}());


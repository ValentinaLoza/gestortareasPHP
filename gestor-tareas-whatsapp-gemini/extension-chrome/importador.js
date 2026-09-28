(function () {
  "use strict";

  if (!location.pathname.endsWith("/importar_whatsapp.php")) {
    return;
  }

  chrome.runtime.sendMessage({ tipo: "OBTENER_CHAT_PENDIENTE" }, (respuesta) => {
    if (chrome.runtime.lastError || !respuesta || !respuesta.ok || !respuesta.payload) {
      return;
    }

    const payload = respuesta.payload;
    const chat = document.getElementById("chat_nombre");
    const mensajes = document.getElementById("mensajes");
    const cantidad = document.getElementById("cantidad_mensajes");
    const adjuntos = document.getElementById("adjuntos_json");
    const estadoAdjuntos = document.getElementById("attachmentStatus");

    if (!chat || !mensajes || !cantidad || !adjuntos) {
      return;
    }

    chat.value = String(payload.chatNombre || "Chat de WhatsApp");
    mensajes.value = String(payload.mensajes || "");
    cantidad.value = Number(payload.cantidadMensajes || 0);
    adjuntos.value = JSON.stringify(Array.isArray(payload.adjuntos) ? payload.adjuntos : []);

    if (estadoAdjuntos) {
      const cantidadAdjuntos = Array.isArray(payload.adjuntos) ? payload.adjuntos.length : 0;
      const omitidos = Number(payload.adjuntosOmitidos || 0);
      const detalle = cantidadAdjuntos > 0
        ? payload.adjuntos.map((adjunto) => {
          const nombre = String(adjunto.nombre || "Adjunto");
          const autor = String(adjunto.autor || "Autor no identificado");
          return nombre + " — " + autor;
        }).join(" | ")
        : "";
      estadoAdjuntos.textContent = cantidadAdjuntos === 0
        ? "No se encontraron imágenes o PDF cargados en los mensajes visibles."
        : cantidadAdjuntos + (cantidadAdjuntos === 1
          ? " adjunto detectado automáticamente"
          : " adjuntos detectados automáticamente")
          + (omitidos > 0 ? " (" + omitidos + " omitidos por tamaño o formato). " : ". ")
          + detalle;
      estadoAdjuntos.classList.toggle("ready", cantidadAdjuntos > 0);
    }

    [chat, mensajes, cantidad, adjuntos].forEach((elemento) => {
      elemento.dispatchEvent(new Event("input", { bubbles: true }));
      elemento.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
}());

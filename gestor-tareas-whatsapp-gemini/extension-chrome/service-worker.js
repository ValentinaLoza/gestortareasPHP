const CONFIGURACION_INICIAL = {
  gestorUrl: "https://gestortareasavex.site.je"
};

const GESTOR_URL_ANTERIOR = "https://gestortareasavex.infinityfreeapp.com";

async function capturarAdjuntosDesdePagina() {
  const MAX_ARCHIVOS = 5;
  const MAX_ARCHIVO_BYTES = 3 * 1024 * 1024;
  const MAX_TOTAL_BYTES = 4 * 1024 * 1024;
  const TIPOS_PERMITIDOS = new Set([
    "image/jpeg",
    "image/png",
    "image/webp",
    "application/pdf"
  ]);

  function limpiar(texto) {
    return String(texto || "").replace(/\s+/g, " ").trim();
  }

  function obtenerAutor(raiz) {
    const elementoMeta = raiz.matches("[data-pre-plain-text]")
      ? raiz
      : raiz.querySelector("[data-pre-plain-text]");
    const meta = elementoMeta ? elementoMeta.getAttribute("data-pre-plain-text") : "";
    const coincidencia = String(meta || "").match(/\]\s*([^:]+):\s*$/);

    if (coincidencia && limpiar(coincidencia[1])) {
      return limpiar(coincidencia[1]);
    }

    return raiz.matches(".message-out") || raiz.classList.contains("message-out")
      ? "Tú"
      : "Participante no identificado";
  }

  function obtenerNombre(elemento, raiz, indice, mime) {
    const descarga = elemento.getAttribute("download");
    const atributos = [
      descarga,
      elemento.getAttribute("aria-label"),
      elemento.getAttribute("title"),
      elemento.getAttribute("alt")
    ].map(limpiar).filter(Boolean);
    const textoRaiz = limpiar(raiz.innerText || raiz.textContent);
    const archivoEnTexto = textoRaiz.match(/[^\\/:*?"<>|\n]+\.(?:pdf|jpe?g|png|webp)\b/i);

    if (archivoEnTexto) {
      return limpiar(archivoEnTexto[0]);
    }

    const nombreAtributo = atributos.find((valor) => /\.(?:pdf|jpe?g|png|webp)$/i.test(valor));
    if (nombreAtributo) {
      return nombreAtributo;
    }

    return mime === "application/pdf"
      ? "documento-whatsapp-" + indice + ".pdf"
      : "imagen-whatsapp-" + indice + ".jpg";
  }

  function blobAData(blob) {
    return new Promise((resolver, rechazar) => {
      const lector = new FileReader();
      lector.onload = () => resolver(String(lector.result || ""));
      lector.onerror = () => rechazar(new Error("No se pudo leer un adjunto."));
      lector.readAsDataURL(blob);
    });
  }

  async function comprimirImagen(blob) {
    if (blob.size <= 900 * 1024) {
      return blob;
    }

    try {
      const bitmap = await createImageBitmap(blob);
      const escala = Math.min(1, 1800 / Math.max(bitmap.width, bitmap.height));
      const ancho = Math.max(1, Math.round(bitmap.width * escala));
      const alto = Math.max(1, Math.round(bitmap.height * escala));
      const canvas = document.createElement("canvas");
      canvas.width = ancho;
      canvas.height = alto;
      const contexto = canvas.getContext("2d");
      contexto.drawImage(bitmap, 0, 0, ancho, alto);
      bitmap.close();

      const comprimido = await new Promise((resolver) => {
        canvas.toBlob(resolver, "image/jpeg", 0.82);
      });

      return comprimido || blob;
    } catch (error) {
      return blob;
    }
  }

  const candidatos = [];
  const fuentesVistas = new Set();
  const raices = Array.from(document.querySelectorAll(
    "#main .message-in, #main .message-out, #main [data-id]"
  ));

  raices.forEach((raiz) => {
    const autor = obtenerAutor(raiz);

    raiz.querySelectorAll("img[src]").forEach((imagen) => {
      const fuente = imagen.currentSrc || imagen.getAttribute("src") || "";
      const ancho = imagen.naturalWidth || imagen.width || 0;
      const alto = imagen.naturalHeight || imagen.height || 0;

      if (!fuente || fuentesVistas.has(fuente) || (ancho < 100 && alto < 100)) {
        return;
      }

      if (/^data:image\/(?:svg\+xml|gif)/i.test(fuente)) {
        return;
      }

      fuentesVistas.add(fuente);
      candidatos.push({ elemento: imagen, raiz, autor, fuente, tipoSugerido: "image/jpeg" });
    });

    raiz.querySelectorAll("a[href], embed[src], object[data]").forEach((elemento) => {
      const fuente = elemento.getAttribute("href")
        || elemento.getAttribute("src")
        || elemento.getAttribute("data")
        || "";
      const nombre = limpiar(elemento.getAttribute("download") || elemento.getAttribute("title"));
      const pareceAdjunto = /^(?:blob:|data:|https?:)/i.test(fuente)
        && (/\.pdf(?:$|[?#])/i.test(fuente) || /\.pdf$/i.test(nombre) || elemento.tagName !== "A");

      if (!pareceAdjunto || fuentesVistas.has(fuente)) {
        return;
      }

      fuentesVistas.add(fuente);
      candidatos.push({ elemento, raiz, autor, fuente, tipoSugerido: "application/pdf" });
    });
  });

  const adjuntos = [];
  let totalBytes = 0;
  let omitidos = 0;

  for (const candidato of candidatos) {
    if (adjuntos.length >= MAX_ARCHIVOS) {
      omitidos += 1;
      continue;
    }

    try {
      const respuesta = await fetch(candidato.fuente, { credentials: "include" });
      if (!respuesta.ok) {
        throw new Error("No se pudo descargar el contenido temporal.");
      }

      let blob = await respuesta.blob();
      let mime = String(blob.type || candidato.tipoSugerido).split(";")[0].toLowerCase();

      if (candidato.elemento.tagName === "IMG" && !mime.startsWith("image/")) {
        mime = candidato.tipoSugerido;
      }

      if (mime.startsWith("image/")) {
        blob = await comprimirImagen(blob);
        mime = String(blob.type || "image/jpeg").split(";")[0].toLowerCase();
      }

      if (!TIPOS_PERMITIDOS.has(mime) || blob.size <= 0 || blob.size > MAX_ARCHIVO_BYTES) {
        omitidos += 1;
        continue;
      }

      if (totalBytes + blob.size > MAX_TOTAL_BYTES) {
        omitidos += 1;
        continue;
      }

      const dataUrl = await blobAData(blob);
      const base64 = dataUrl.includes(",") ? dataUrl.split(",", 2)[1] : "";
      if (!base64) {
        omitidos += 1;
        continue;
      }

      adjuntos.push({
        nombre: obtenerNombre(candidato.elemento, candidato.raiz, adjuntos.length + 1, mime),
        mime_type: mime,
        base64,
        autor: candidato.autor
      });
      totalBytes += blob.size;
    } catch (error) {
      omitidos += 1;
    }
  }

  return {
    adjuntos,
    detectados: candidatos.length,
    omitidos
  };
}

chrome.runtime.onInstalled.addListener(async () => {
  const actual = await chrome.storage.local.get("gestorUrl");

  if (!actual.gestorUrl || actual.gestorUrl === GESTOR_URL_ANTERIOR) {
    await chrome.storage.local.set(CONFIGURACION_INICIAL);
  }
});

chrome.action.onClicked.addListener(() => {
  chrome.runtime.openOptionsPage();
});

chrome.runtime.onMessage.addListener((mensaje, remitente, responder) => {
  if (mensaje && mensaje.tipo === "CAPTURAR_ADJUNTOS") {
    (async () => {
      if (!remitente.tab || !remitente.tab.id) {
        throw new Error("No se pudo identificar la pestaña de WhatsApp.");
      }

      const resultados = await chrome.scripting.executeScript({
        target: { tabId: remitente.tab.id },
        world: "MAIN",
        func: capturarAdjuntosDesdePagina
      });
      const captura = resultados && resultados[0] ? resultados[0].result : null;
      responder({ ok: true, captura: captura || { adjuntos: [], detectados: 0, omitidos: 0 } });
    })().catch((error) => responder({ ok: false, error: error.message }));

    return true;
  }

  if (mensaje && mensaje.tipo === "ABRIR_IMPORTADOR") {
    (async () => {
      const configuracion = await chrome.storage.local.get(CONFIGURACION_INICIAL);
      const gestorUrl = String(configuracion.gestorUrl || CONFIGURACION_INICIAL.gestorUrl)
        .trim()
        .replace(/\/$/, "");

      await chrome.storage.session.set({
        chatPendiente: mensaje.payload,
        chatPendienteGuardadoEn: Date.now()
      });

      await chrome.tabs.create({
        url: gestorUrl + "/importar_whatsapp.php?origen=extension"
      });

      responder({ ok: true });
    })().catch((error) => {
      responder({ ok: false, error: error.message });
    });

    return true;
  }

  if (mensaje && mensaje.tipo === "OBTENER_CHAT_PENDIENTE") {
    chrome.storage.session.get(["chatPendiente", "chatPendienteGuardadoEn"])
      .then(async (datos) => {
        responder({
          ok: true,
          payload: datos.chatPendiente || null,
          guardadoEn: datos.chatPendienteGuardadoEn || null
        });
        await chrome.storage.session.remove(["chatPendiente", "chatPendienteGuardadoEn"]);
      })
      .catch((error) => responder({ ok: false, error: error.message }));

    return true;
  }

  return false;
});

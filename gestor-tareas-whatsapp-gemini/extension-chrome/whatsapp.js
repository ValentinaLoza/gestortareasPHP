(function () {
  "use strict";

  const ID_PANEL = "avex-whatsapp-summary-panel";
  const LIMITE_CARACTERES = 170000;
  const CLAVE_ESTADO_PANEL = "avex_whatsapp_panel_estado_v1";
  const MARGEN_PANEL = 8;
  let ajusteResizeRegistrado = false;

  function normalizarTexto(texto) {
    return String(texto || "")
      .replace(/\u200e|\u200f/g, "")
      .replace(/[ \t]+\n/g, "\n")
      .replace(/\n{3,}/g, "\n\n")
      .trim();
  }

  function obtenerNombreChat() {
    const candidatos = [
      "#main header [data-testid='conversation-info-header-chat-title']",
      "#main header [data-testid*='chat-title']",
      "#main header [role='heading']",
      "#main header h1",
      "#main header span[title]",
      "#main header [dir='auto'][title]"
    ];

    for (const selector of candidatos) {
      const elemento = document.querySelector(selector);
      const nombre = elemento
        ? normalizarTexto(elemento.getAttribute("title") || elemento.textContent)
        : "";

      if (nombre) {
        return nombre;
      }
    }

    const encabezado = document.querySelector("#main header");
    if (encabezado) {
      const lineas = normalizarTexto(encabezado.innerText || encabezado.textContent)
        .split("\n")
        .map((linea) => normalizarTexto(linea))
        .filter(Boolean);

      if (lineas.length > 0) {
        return lineas[0];
      }
    }

    return "Chat de WhatsApp";
  }

  function obtenerTextoMensaje(elemento) {
    const bloques = Array.from(elemento.querySelectorAll(".selectable-text"));
    const bloquesFinales = bloques.filter((bloque) => !bloque.querySelector(".selectable-text"));
    const textos = [];
    const vistos = new Set();

    (bloquesFinales.length > 0 ? bloquesFinales : [elemento]).forEach((bloque) => {
      const texto = normalizarTexto(bloque.innerText || bloque.textContent);
      if (texto && !vistos.has(texto)) {
        vistos.add(texto);
        textos.push(texto);
      }
    });

    return normalizarTexto(textos.join("\n"));
  }

  function crearMetaAlternativa(elemento) {
    const textoCompleto = normalizarTexto(elemento.innerText || elemento.textContent);
    const horas = textoCompleto.match(/\b\d{1,2}:\d{2}\b/g);
    const hora = horas && horas.length > 0 ? horas[horas.length - 1] : "Sin hora";
    const esPropio = elemento.matches(".message-out")
      || elemento.classList.contains("message-out")
      || Boolean(elemento.querySelector(".message-out"));

    return "[" + hora + "] " + (esPropio ? "Tú" : "Participante") + ":";
  }

  function extraerMensajes() {
    const raices = new Set();
    const claves = new Set();
    const lineas = [];

    document.querySelectorAll("#main [data-pre-plain-text]").forEach((elemento) => {
      const raiz = elemento.closest(".message-in, .message-out, [data-id]") || elemento;
      raices.add(raiz);
    });

    document.querySelectorAll("#main .message-in, #main .message-out").forEach((elemento) => {
      raices.add(elemento);
    });

    document.querySelectorAll("#main [data-id]").forEach((elemento) => {
      if (elemento.querySelector(".selectable-text")) {
        raices.add(elemento.closest(".message-in, .message-out") || elemento);
      }
    });

    raices.forEach((elemento) => {
      const elementoMeta = elemento.matches("[data-pre-plain-text]")
        ? elemento
        : elemento.querySelector("[data-pre-plain-text]");
      const meta = elementoMeta
        ? normalizarTexto(elementoMeta.getAttribute("data-pre-plain-text"))
        : crearMetaAlternativa(elemento);
      const texto = obtenerTextoMensaje(elemento);

      if (!texto || texto === meta) {
        return;
      }

      const clave = meta + "|" + texto;
      if (claves.has(clave)) {
        return;
      }

      claves.add(clave);
      lineas.push((meta ? meta + " " : "") + texto);
    });

    while (lineas.join("\n").length > LIMITE_CARACTERES && lineas.length > 1) {
      lineas.shift();
    }

    return {
      mensajes: lineas.join("\n"),
      cantidad: lineas.length,
      filtroHoyAplicado: false
    };
  }

  function mostrarEstado(texto, tipo) {
    const estado = document.getElementById("avex-whatsapp-summary-status");
    if (!estado) {
      return;
    }

    estado.textContent = texto;
    estado.dataset.tipo = tipo || "normal";
  }

  function limitar(numero, minimo, maximo) {
    return Math.min(Math.max(numero, minimo), Math.max(minimo, maximo));
  }

  function ajustarPanelALaVentana(panel) {
    if (!panel) {
      return;
    }

    const rect = panel.getBoundingClientRect();
    if (!Number.isFinite(rect.left) || !Number.isFinite(rect.top)) {
      return;
    }

    const izquierda = limitar(rect.left, MARGEN_PANEL, window.innerWidth - rect.width - MARGEN_PANEL);
    const arriba = limitar(rect.top, MARGEN_PANEL, window.innerHeight - rect.height - MARGEN_PANEL);
    panel.style.left = Math.round(izquierda) + "px";
    panel.style.top = Math.round(arriba) + "px";
    panel.style.right = "auto";
    panel.style.bottom = "auto";
  }

  function guardarEstadoPanel(panel) {
    if (!panel || !chrome.storage || !chrome.storage.local) {
      return;
    }

    const rect = panel.getBoundingClientRect();
    chrome.storage.local.set({
      [CLAVE_ESTADO_PANEL]: {
        left: Math.round(rect.left),
        top: Math.round(rect.top),
        minimizado: panel.classList.contains("avex-panel-minimizado")
      }
    }).catch(() => {});
  }

  async function restaurarEstadoPanel(panel) {
    if (!panel || !chrome.storage || !chrome.storage.local) {
      return;
    }

    try {
      const datos = await chrome.storage.local.get(CLAVE_ESTADO_PANEL);
      const estado = datos ? datos[CLAVE_ESTADO_PANEL] : null;
      if (!estado || typeof estado !== "object") {
        return;
      }

      if (estado.minimizado) {
        panel.classList.add("avex-panel-minimizado");
      }

      if (Number.isFinite(estado.left) && Number.isFinite(estado.top)) {
        panel.style.left = estado.left + "px";
        panel.style.top = estado.top + "px";
        panel.style.right = "auto";
        panel.style.bottom = "auto";
      }

      requestAnimationFrame(() => ajustarPanelALaVentana(panel));
    } catch (error) {
      // El panel sigue funcionando aunque Chrome no pueda recuperar su posición.
    }
  }

  function cambiarPanelMinimizado(panel, minimizado) {
    panel.classList.toggle("avex-panel-minimizado", minimizado);
    const botonMinimizar = document.getElementById("avex-whatsapp-summary-minimize");
    if (botonMinimizar) {
      botonMinimizar.setAttribute("aria-expanded", minimizado ? "false" : "true");
    }

    requestAnimationFrame(() => {
      ajustarPanelALaVentana(panel);
      guardarEstadoPanel(panel);
    });
  }

  function habilitarArrastre(panel, manejador) {
    if (!panel || !manejador) {
      return;
    }

    manejador.addEventListener("pointerdown", (evento) => {
      if (evento.button !== 0 || evento.target.closest("button")) {
        return;
      }

      const rect = panel.getBoundingClientRect();
      const inicioX = evento.clientX;
      const inicioY = evento.clientY;
      const inicioIzquierda = rect.left;
      const inicioArriba = rect.top;

      panel.classList.add("avex-panel-arrastrando");
      if (typeof manejador.setPointerCapture === "function") {
        manejador.setPointerCapture(evento.pointerId);
      }

      const mover = (movimiento) => {
        const izquierda = limitar(
          inicioIzquierda + movimiento.clientX - inicioX,
          MARGEN_PANEL,
          window.innerWidth - panel.offsetWidth - MARGEN_PANEL
        );
        const arriba = limitar(
          inicioArriba + movimiento.clientY - inicioY,
          MARGEN_PANEL,
          window.innerHeight - panel.offsetHeight - MARGEN_PANEL
        );
        panel.style.left = Math.round(izquierda) + "px";
        panel.style.top = Math.round(arriba) + "px";
        panel.style.right = "auto";
        panel.style.bottom = "auto";
      };

      const terminar = () => {
        manejador.removeEventListener("pointermove", mover);
        manejador.removeEventListener("pointerup", terminar);
        manejador.removeEventListener("pointercancel", terminar);
        panel.classList.remove("avex-panel-arrastrando");
        guardarEstadoPanel(panel);
      };

      manejador.addEventListener("pointermove", mover);
      manejador.addEventListener("pointerup", terminar);
      manejador.addEventListener("pointercancel", terminar);
      evento.preventDefault();
    });
  }

  async function enviarAlGestor() {
    mostrarEstado("Leyendo los mensajes cargados...", "normal");

    const chatNombre = obtenerNombreChat();
    const resultado = extraerMensajes();

    if (!resultado.mensajes || resultado.cantidad === 0) {
      mostrarEstado("No encontré mensajes. Abrí un chat y desplazate hasta los mensajes de hoy.", "error");
      return;
    }

    mostrarEstado("Buscando imágenes y documentos cargados en el chat...", "normal");
    const respuestaAdjuntos = await chrome.runtime.sendMessage({ tipo: "CAPTURAR_ADJUNTOS" });
    const captura = respuestaAdjuntos && respuestaAdjuntos.ok && respuestaAdjuntos.captura
      ? respuestaAdjuntos.captura
      : { adjuntos: [], detectados: 0, omitidos: 0 };

    const respuesta = await chrome.runtime.sendMessage({
      tipo: "ABRIR_IMPORTADOR",
      payload: {
        chatNombre,
        mensajes: resultado.mensajes,
        cantidadMensajes: resultado.cantidad,
        adjuntos: captura.adjuntos || [],
        adjuntosDetectados: Number(captura.detectados || 0),
        adjuntosOmitidos: Number(captura.omitidos || 0),
        filtroHoyAplicado: resultado.filtroHoyAplicado,
        capturadoEn: new Date().toISOString()
      }
    });

    if (!respuesta || !respuesta.ok) {
      mostrarEstado(
        "No pude abrir el gestor: " + (respuesta && respuesta.error ? respuesta.error : "error desconocido"),
        "error"
      );
      return;
    }

    mostrarEstado(
      resultado.cantidad + " mensajes y " + (captura.adjuntos || []).length + " adjuntos enviados para revisión.",
      "ok"
    );
  }

  function crearPanel() {
    if (document.getElementById(ID_PANEL)) {
      return;
    }

    const panel = document.createElement("section");
    panel.id = ID_PANEL;
    panel.innerHTML = [
      "<div id='avex-whatsapp-summary-handle' class='avex-summary-handle' title='Arrastrá para mover'>",
      "<strong><span aria-hidden='true'>📋</span> Resumen AVEX</strong>",
      "<button type='button' id='avex-whatsapp-summary-minimize' aria-label='Minimizar' aria-expanded='true' title='Minimizar'>−</button>",
      "</div>",
      "<div class='avex-summary-content'>",
      "<button type='button' id='avex-whatsapp-summary-button'>",
      "Enviar chat al gestor",
      "</button>",
      "<small>Cargá los mensajes y adjuntos de hoy; la extensión los detectará automáticamente.</small>",
      "<div id='avex-whatsapp-summary-status' aria-live='polite'></div>",
      "</div>",
      "<button type='button' id='avex-whatsapp-summary-restore' aria-label='Abrir Resumen AVEX' title='Abrir Resumen AVEX'>📋</button>"
    ].join("");

    document.body.appendChild(panel);
    const manejador = document.getElementById("avex-whatsapp-summary-handle");
    const botonMinimizar = document.getElementById("avex-whatsapp-summary-minimize");
    const botonRestaurar = document.getElementById("avex-whatsapp-summary-restore");

    habilitarArrastre(panel, manejador);
    botonMinimizar.addEventListener("click", () => cambiarPanelMinimizado(panel, true));
    botonRestaurar.addEventListener("click", () => cambiarPanelMinimizado(panel, false));
    restaurarEstadoPanel(panel);

    if (!ajusteResizeRegistrado) {
      window.addEventListener("resize", () => {
        const panelActual = document.getElementById(ID_PANEL);
        if (panelActual) {
          ajustarPanelALaVentana(panelActual);
          guardarEstadoPanel(panelActual);
        }
      });
      ajusteResizeRegistrado = true;
    }

    document.getElementById("avex-whatsapp-summary-button").addEventListener("click", () => {
      enviarAlGestor().catch((error) => {
        mostrarEstado("Ocurrió un error: " + error.message, "error");
      });
    });
  }

  crearPanel();

  const observador = new MutationObserver(() => {
    if (!document.getElementById(ID_PANEL)) {
      crearPanel();
    }
  });

  observador.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}());

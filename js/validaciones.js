/**
 * CyberPOS — validaciones.js
 * Validaciones de valores limite en el cliente (complementan al backend).
 * Devuelven { ok: boolean, valor: <dato limpio>, error: <mensaje> }.
 */

(function () {
  'use strict';

  function textoLimpio(valor) {
    return String(valor === null || valor === undefined ? '' : valor).trim();
  }

  const Validacion = {
    /** Texto obligatorio/opcional con longitud maxima (VARCHAR de la BD). */
    texto(valor, opciones) {
      const { campo = 'campo', max = 255, min = 1, requerido = true } = opciones || {};
      const limpio = textoLimpio(valor);
      if (requerido && limpio === '') {
        return { ok: false, error: `El campo ${campo} no puede estar vacio ni contener solo espacios.` };
      }
      if (limpio !== '' && limpio.length < min) {
        return { ok: false, error: `El campo ${campo} debe tener al menos ${min} caracteres.` };
      }
      if (limpio.length > max) {
        return { ok: false, error: `El campo ${campo} no puede exceder ${max} caracteres.` };
      }
      return { ok: true, valor: limpio };
    },

    /** Numero entero (rechaza decimales, letras y vacios). */
    entero(valor, opciones) {
      const { campo = 'numero', min = null, max = null } = opciones || {};
      const limpio = textoLimpio(valor);
      if (!/^[+-]?\d+$/.test(limpio)) {
        return { ok: false, error: `El campo ${campo} debe ser un numero entero sin decimales.` };
      }
      const numero = Number(limpio);
      if (!Number.isSafeInteger(numero)) {
        return { ok: false, error: `El campo ${campo} no es un numero valido.` };
      }
      if (min !== null && numero < min) {
        return { ok: false, error: `El campo ${campo} debe ser mayor o igual a ${min}.` };
      }
      if (max !== null && numero > max) {
        return { ok: false, error: `El campo ${campo} no puede ser mayor a ${max}.` };
      }
      return { ok: true, valor: numero };
    },

    /** Numero decimal. Por defecto exige ser mayor al minimo (no inclusivo). */
    decimal(valor, opciones) {
      const { campo = 'monto', min = null, max = null, inclusivo = false } = opciones || {};
      const limpio = textoLimpio(valor);
      if (limpio === '' || !Number.isFinite(Number(limpio))) {
        return { ok: false, error: `El campo ${campo} debe ser un valor numerico valido.` };
      }
      const numero = Number(limpio);
      if (min !== null && (inclusivo ? numero < min : numero <= min)) {
        return {
          ok: false,
          error: inclusivo
            ? `El campo ${campo} no puede ser negativo.`
            : `El campo ${campo} debe ser mayor a ${min}.`
        };
      }
      if (max !== null && numero > max) {
        return { ok: false, error: `El campo ${campo} no puede ser mayor a ${max}.` };
      }
      return { ok: true, valor: numero };
    }
  };

  window.Validacion = Validacion;
})();

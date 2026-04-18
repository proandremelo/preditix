/**
 * Área (m²): apenas dígitos, no máximo um separador decimal (vírgula ou ponto),
 * até 10 dígitos na parte inteira e 2 na decimal — alinhado à validação em Instalacao::validarAreaM2.
 */
(function () {
    var MAX_INT = 10;
    var MAX_FRAC = 2;

    function sanitizar(raw) {
        var s = String(raw).replace(/[^\d.,]/g, '');
        var sepIdx = -1;
        var sep = '';
        for (var i = 0; i < s.length; i++) {
            if (s[i] === '.' || s[i] === ',') {
                sepIdx = i;
                sep = s[i];
                break;
            }
        }
        var intPart;
        var fracPart;
        if (sepIdx < 0) {
            intPart = s.replace(/[^\d]/g, '').slice(0, MAX_INT);
            return intPart;
        }
        intPart = s.slice(0, sepIdx).replace(/[^\d]/g, '').slice(0, MAX_INT);
        fracPart = s.slice(sepIdx + 1).replace(/[^\d]/g, '').slice(0, MAX_FRAC);
        if (intPart === '' && fracPart === '') {
            return '';
        }
        if (intPart === '') {
            return '0' + sep + fracPart;
        }
        return intPart + sep + fracPart;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-area-m2]').forEach(function (el) {
            function aplicar() {
                var n = sanitizar(el.value);
                if (n !== el.value) {
                    el.value = n;
                }
            }
            el.addEventListener('input', aplicar);
            el.addEventListener('paste', function () {
                requestAnimationFrame(aplicar);
            });
            aplicar();
        });
    });
})();

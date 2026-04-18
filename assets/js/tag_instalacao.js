/**
 * Restringe o campo tag a letras (A–Z, a–z) e números, no máximo maxlength.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-tag-alfanumerica]').forEach(function (el) {
        var max = parseInt(el.getAttribute('maxlength'), 10) || 50;

        function sanitizar() {
            var v = el.value.replace(/[^A-Za-z0-9]/g, '');
            if (v.length > max) {
                v = v.slice(0, max);
            }
            if (v !== el.value) {
                el.value = v;
            }
        }

        el.addEventListener('input', sanitizar);
        el.addEventListener('paste', function () {
            requestAnimationFrame(sanitizar);
        });
        sanitizar();
    });
});

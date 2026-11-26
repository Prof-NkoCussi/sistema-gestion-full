// js/validar.js: revisa el formulario del alumno antes de enviarlo
// Es una ayuda para quien carga los datos: PHP vuelve a validar todo en el servidor

// El formulario de alta y modificación (id="form-alumno", en form_alumno.php)
const formulario = document.querySelector('#form-alumno');

// mensaje(): el error de un campo, o '' si está bien (las mismas reglas que validar_alumno() en PHP)
function mensaje(campo) {
    const valor = campo.value.trim();
    if (campo.name === 'dni') {
        // Como en PHP: se sacan puntos y espacios, y tienen que quedar 7 u 8 números
        const dni = valor.replace(/[.\s]/g, '');
        return /^\d{7,8}$/.test(dni) ? '' : 'El DNI tiene que tener 7 u 8 números.';
    }
    if (campo.name === 'apellido') {
        return valor !== '' && valor.length <= 60 ? '' : 'El apellido es obligatorio (hasta 60 letras).';
    }
    if (campo.name === 'nombre') {
        return valor !== '' && valor.length <= 60 ? '' : 'El nombre es obligatorio (hasta 60 letras).';
    }
    // curso_id: en el <select>, «Elegí…» tiene value vacío
    return valor !== '' ? '' : 'Elegí un curso de la lista.';
}

// revisar(): muestra el mensaje debajo del campo y devuelve true si está bien
function revisar(campo) {
    const texto = mensaje(campo);
    // Cada campo tiene su <p> de error: error-dni, error-apellido…
    // textContent: el texto se pone como texto, nunca como HTML
    document.querySelector('#error-' + campo.name).textContent = texto;
    // aria-invalid: lo pinta de rojo (CSS) y el lector de pantalla avisa que tiene un error
    campo.setAttribute('aria-invalid', texto === '' ? 'false' : 'true');
    return texto === '';
}

// noValidate: los mensajes los pone este archivo, no los globos del navegador
// (sin JavaScript, siguen funcionando required y pattern del HTML)
formulario.noValidate = true;
// Los cuatro campos, buscados por su name dentro del formulario
const campos = ['dni', 'apellido', 'nombre', 'curso_id'].map((nombre) => formulario.elements[nombre]);

campos.forEach((campo) => {
    // blur: al salir del campo, se revisa ese campo
    campo.addEventListener('blur', () => revisar(campo));
    // input: mientras se escribe, si ya estaba marcado con error, se vuelve a revisar
    // (así el rojo se va apenas se corrige, sin esperar a salir del campo)
    campo.addEventListener('input', () => {
        if (campo.getAttribute('aria-invalid') === 'true') {
            revisar(campo);
        }
    });
});

// submit: al enviar se revisan todos; si alguno está mal, el formulario no sale
formulario.addEventListener('submit', (evento) => {
    const malos = campos.filter((campo) => !revisar(campo));
    if (malos.length > 0) {
        evento.preventDefault();   // cancela el envío: no llega ningún pedido al servidor
        malos[0].focus();          // el cursor va al primer campo con error
    }
});

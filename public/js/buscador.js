// js/buscador.js: filtra la tabla de alumnos mientras se escribe (sin ir al servidor)

// El campo de búsqueda, las filas de la tabla y el texto con la cuenta
const buscador = document.querySelector('#buscador');
const filas = document.querySelectorAll('#tabla-alumnos tbody tr');
const cuenta = document.querySelector('#cuenta');

// normalizar(): minúsculas y sin tildes, para que «gutierrez» encuentre a «Gutiérrez»
function normalizar(texto) {
    // NFD separa cada letra de su tilde (é pasa a e + ´); el replace borra las tildes sueltas
    return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

// input: salta con cada cambio del campo (una letra, un borrado o un pegado)
buscador.addEventListener('input', () => {
    const buscado = normalizar(buscador.value.trim());
    let visibles = 0;
    filas.forEach((fila) => {
        // Las dos primeras celdas: apellido y nombre, y DNI (no el curso ni los enlaces)
        const datos = fila.cells[0].textContent + ' ' + fila.cells[1].textContent;
        const coincide = normalizar(datos).includes(buscado);
        // hidden: esconde la fila, no la borra (al vaciar el campo vuelven todas)
        fila.hidden = !coincide;
        if (coincide) {
            visibles++;
        }
    });
    cuenta.textContent = visibles + ' de ' + filas.length + ' alumnos';
});

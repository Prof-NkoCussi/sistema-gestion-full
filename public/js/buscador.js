// js/buscador.js: busca alumnos en el servidor mientras se escribe (fetch + JSON)

// El campo de búsqueda, la tabla y el texto con la cuenta
const buscador = document.querySelector('#buscador');
const tabla = document.querySelector('#tabla-alumnos');
const cuenta = document.querySelector('#cuenta');
// El temporizador de la última tecla (para esperar a que se deje de escribir)
let espera = null;

// celda(): un <td> con un texto (textContent: el dato nunca se toma como HTML)
function celda(texto) {
    const td = document.createElement('td');
    td.textContent = texto;
    return td;
}

// enlace(): un <a> para la columna de acciones
function enlace(texto, url) {
    const a = document.createElement('a');
    a.href = url;
    a.textContent = texto;
    return a;
}

// dibujar(): vacía la tabla y arma una fila por alumno, igual que las de PHP
function dibujar(alumnos) {
    const cuerpo = tabla.querySelector('tbody');
    // replaceChildren() sin nada: saca todas las filas
    cuerpo.replaceChildren();
    alumnos.forEach((a) => {
        const fila = document.createElement('tr');
        fila.append(celda(a.apellido.toUpperCase() + ', ' + a.nombre), celda(a.dni), celda(a.curso));
        const acciones = document.createElement('td');
        acciones.className = 'acciones';
        acciones.append(enlace('Editar', 'alumno_editar.php?id=' + a.id));
        // data-admin lo escribe PHP; esconder la baja es comodidad: alumno_baja.php igual controla el rol
        if (tabla.dataset.admin === '1') {
            const baja = enlace('Baja', 'alumno_baja.php?id=' + a.id);
            baja.className = 'peligro';
            acciones.append(baja);
        }
        fila.append(acciones);
        cuerpo.append(fila);
    });
}

// buscar(): pide los alumnos a api/alumnos.php y redibuja la tabla
// async: adentro se puede usar await
async function buscar(texto) {
    // encodeURIComponent(): «Muñoz» viaja en la URL como Mu%C3%B1oz
    const url = 'api/alumnos.php?q=' + encodeURIComponent(texto);
    try {
        // await: espera la respuesta sin congelar la página
        const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
        // fetch solo falla si no hay red: un 401 o un 500 se miran con respuesta.ok
        if (!respuesta.ok) {
            throw new Error('el servidor respondió ' + respuesta.status);
        }
        // json(): el texto JSON pasa a ser un objeto de JavaScript
        const datos = await respuesta.json();
        dibujar(datos.alumnos);
        // data-total: cuántos alumnos hay en total (lo escribe PHP en la tabla)
        cuenta.textContent = datos.total + ' de ' + tabla.dataset.total + ' alumnos';
    } catch (error) {
        cuenta.textContent = 'No se pudo buscar: ' + error.message + '.';
    }
}

// input: con cada letra se reinicia la espera; se busca 300 ms después de la última
// Así no sale un pedido al servidor por cada letra
buscador.addEventListener('input', () => {
    clearTimeout(espera);
    espera = setTimeout(() => buscar(buscador.value.trim()), 300);
});

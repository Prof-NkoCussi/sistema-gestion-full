// js/notas.js: en la carga de notas, al cambiar el curso se actualizan sus materias
// Sin JavaScript la página anda igual: se elige el curso y se toca «Ver planilla»

// El formulario de filtros y las listas de curso y materia
const filtros = document.querySelector('.filtros');
const curso = document.querySelector('#curso_id');
const materia = document.querySelector('#materia_id');

// change: se dispara cuando se elige otra opción de la lista
curso.addEventListener('change', () => {
    // Si volvió a «Elegí…», no hay curso para buscar
    if (curso.value === '') {
        return;
    }
    // La materia elegida era del curso anterior: se vacía para no mandarla
    materia.value = '';
    // requestSubmit(): envía el formulario como si se tocara «Ver planilla»
    filtros.requestSubmit();
});

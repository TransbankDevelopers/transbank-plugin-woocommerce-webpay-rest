export class InscriptionDeletion {
    constructor(inscriptionTableSelector, buttonDeleteSelector) {
        this.inscriptionTableSelector = inscriptionTableSelector;
        this.buttonDeleteSelector = buttonDeleteSelector;
    }

    init() {
        const inscriptionTable = document.querySelector(this.inscriptionTableSelector);
        inscriptionTable?.addEventListener?.('click', (e) => {
            e.preventDefault();
            const link = e.target.closest(this.buttonDeleteSelector);

            if (!link)
                return;

            this.handleDelete(link);
        }, true);
    }

    handleDelete(link) {
        const href = link.getAttribute('href');
        if (!href)
            return;

        if (typeof swal !== 'function') {
            window.location.href = href;
            return;
        }

        this.showConfirmation(href);
    }

    showConfirmation(href) {
        swal({
            title: "Eliminar inscripción",
            text: "Esta acción no se puede deshacer. ¿Deseas continuar?",
            icon: "warning",
            className: 'tbk-swal-modal',
            buttons: {
                cancel: {
                    text: "Cancelar",
                    visible: true,
                    closeModal: true,
                },
                confirm: {
                    text: "Eliminar",
                    value: true,
                    closeModal: true,
                },
            },
            dangerMode: true,
        }).then(function (confirmed) {
            if (confirmed) {
                globalThis.location.href = href;
            }
        });
    }
}

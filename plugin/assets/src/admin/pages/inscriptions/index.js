import { InscriptionDeletion } from '../../modules/inscription/inscriptionDeletion';
import { DOMUtils } from '../../utils/dom';

DOMUtils.ready(() => {
    const inscriptionDeletion = new InscriptionDeletion('.transbank_inscriptions', '.tbk-js-delete-inscription');
    inscriptionDeletion.init();
});

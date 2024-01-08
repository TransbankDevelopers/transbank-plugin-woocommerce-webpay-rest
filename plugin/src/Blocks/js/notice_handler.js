const oneClickId = 'transbank_oneclick_mall_rest';
const url = new URL(window.location.href);
const params = new URLSearchParams(url.search);
const hasTbkData = params.has('transbank_status');

const noticeTypes = {
    SUCESS: 'success',
    ERROR: 'error'
}

const oneClickNoticeData = {
    0: { message: 'La tarjeta ha sido inscrita satisfactoriamente. Aún no se realiza ningún cobro. Ahora puedes realizar el pago.',
        type: noticeTypes.SUCESS },
    1: { message: 'La inscripción fue cancelada automáticamente por estar inactiva mucho tiempo.',
        type: noticeTypes.ERROR },
    2: { message: 'No se recibió el token de la inscripción.',
        type: noticeTypes.ERROR },
    3: { message: 'El usuario canceló la inscripción en el formulario de pago.',
        type: noticeTypes.ERROR },
    4: { message: 'La inscripción no se encuentra en estado inicializada.',
        type: noticeTypes.ERROR },
    5: { message: 'Ocurrió un error al ejecutar la inscripción.',
        type: noticeTypes.ERROR },
    6: { message: 'La inscripción de la tarjeta ha sido rechazada.',
        type: noticeTypes.ERROR }
};

const webPayNoticeNoticeData  = {
    7: { message: 'Transacción aprobada',
    type: noticeTypes.SUCESS },
    8: { message: 'El usuario intentó pagar esta orden nuevamente, cuando esta ya estaba pagada.',
    type: noticeTypes.ERROR },
    9: { message: 'El usuario intentó pagar una orden con estado inválido.',
    type: noticeTypes.ERROR },
    10: { message: 'La transacción fue cancelada automáticamente por estar inactiva mucho tiempo en el formulario de pago de Webpay. Puede reintentar el pago',
    type: noticeTypes.ERROR },
    11: { message: 'El usuario canceló la transacción en el formulario de pago, pero esta orden ya estaba pagada o en un estado diferente a INICIALIZADO',
    type: noticeTypes.ERROR },
    12: { message: 'Cancelaste la transacción durante el formulario de Webpay Plus.',
    type: noticeTypes.ERROR },
    13: { message: 'El pago es inválido.',
    type: noticeTypes.ERROR },
    14: { message: 'La transacción no se encuentra en estado inicializada.',
    type: noticeTypes.ERROR },
    15: { message: 'El commit de la transacción ha sido rechazada en Transbank',
    type: noticeTypes.ERROR },
    16: { message: 'Ocurrió un error al ejecutar el commit de la transacción.',
    type: noticeTypes.ERROR },
    17: { message: 'Ocurrió un error inesperado.',
    type: noticeTypes.ERROR }
};

export const noticeHandler = ( productId ) => {
    if (hasTbkData) {
        const productNoticeData = productId == oneClickId ? oneClickNoticeData : webPayNoticeNoticeData;
        const statusCode = params.get('transbank_status');
        if (!productNoticeData.hasOwnProperty(statusCode)) {
            return;
        }
        const noticeMessage = productNoticeData[statusCode]['message'];
        const notificationType = productNoticeData[statusCode]['type'];
        switch (notificationType){
            case noticeTypes.SUCESS:
                wp.data.dispatch('core/notices').createSuccessNotice( noticeMessage, { context: 'wc/checkout' } );
                break;
            case noticeTypes.ERROR:
                wp.data.dispatch('core/notices').createErrorNotice( noticeMessage, { context: 'wc/checkout' } );
                break;
        }
    }
};

const statusColors = {
    Inicializada: "tbk-badge-warning",
    Capturada: "tbk-badge-success",
    Autorizada: "tbk-badge-success",
    Fallida: "tbk-badge-error",
    Anulada: "tbk-badge-info",
    Reversada: "tbk-badge-info",
    "Parcialmente anulada": "tbk-badge-info",
};

export function createTransactionSchema() {
    return {
        fields: {
            vci: {
                label: "VCI"
            },
            status: {
                label: "Estado",
                className: (value) => ["tbk-badge", statusColors[value]]
            },
            responseCode: {
                label: "Código de respuesta"
            },
            amount: {
                label: "Monto"
            },
            authorizationCode: {
                label: "Código de autorización"
            },
            accountingDate: {
                label: "Fecha contable"
            },
            paymentType: {
                label: "Tipo de pago"
            },
            installmentType: {
                label: "Tipo de cuota"
            },
            installmentNumber: {
                label: "Número de cuotas"
            },
            installmentAmount: {
                label: "Monto cuota"
            },
            sessionId: {
                label: "ID de sesión"
            },
            buyOrder: {
                label: "Orden de compra"
            },
            buyOrderMall: {
                label: "Orden de compra mall"
            },
            buyOrderStore: {
                label: "Orden de compra tienda"
            },
            cardNumber: {
                label: "Número de tarjeta",
                className: ["tbk-field-value--full-width"]
            },
            transactionDate: {
                label: "Fecha transacción"
            },
            transactionTime: {
                label: "Hora transacción"
            },
            balance: {
                label: "Balance"
            }
        }
    };
}

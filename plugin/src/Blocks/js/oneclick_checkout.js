import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { noticeHandler } from './notice_handler';
const { useEffect } = window.wp.element;

const settings = getSetting( 'transbank_oneclick_mall_rest_data', {} );
const label = decodeEntities( settings.title );

noticeHandler(settings.id);

const Content = ( props ) => {

	const {eventRegistration, emitResponse} = props;
	const { onCheckoutFail } = eventRegistration;
	useEffect( () => {
		const onError = ( { processingResponse } ) => {
			if ( processingResponse.paymentDetails.errorMessage ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: processingResponse.paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS
				};
			}
			return true;
		};
		const unsubscribe = onCheckoutFail( onError );
		return unsubscribe;
	}, [ onCheckoutFail ] );

	return decodeEntities( settings.description );
};

const Label = () => {
	const title = decodeEntities( settings.title );
	const imagePath = settings.icon;
	const paymentImage = (
		<img src={imagePath} alt="oneclick logo"/>
	);
	return (
		<div>
			{title}
			{paymentImage}
		</div>
	);
};

const TransbankOneclickBlocks = {
	name: settings.id,
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports
	}
};

registerPaymentMethod( TransbankOneclickBlocks );

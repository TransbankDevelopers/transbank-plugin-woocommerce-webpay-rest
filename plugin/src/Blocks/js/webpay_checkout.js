import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { noticeHandler } from './notice_handler';

const settings = getSetting( 'transbank_webpay_plus_rest_data', {} );
const label = decodeEntities( settings.title );

noticeHandler(settings.id);

const Content = () => {
	return decodeEntities( settings.description );
};

const Label = () => {
	const title = decodeEntities( settings.title );
	const imagePath = settings.icon;
	const paymentImage = (
		<img src={imagePath} alt="webpay plus logo"/>
	);
	return (
		<div>
			{title}
			{paymentImage}
		</div>
	);
};

const TransbankWebpayBlocks = {
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

registerPaymentMethod( TransbankWebpayBlocks );

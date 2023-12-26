import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'transbank_oneclick_mall_rest_data', {} );

const label = decodeEntities( settings.title );

const Content = () => {
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
	name: "transbank_oneclick_mall_rest",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( TransbankOneclickBlocks );

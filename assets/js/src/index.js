import { createRoot } from '@wordpress/element';
import LogsTable from '@components/LogsTable';
import './style.css';

const root = document.getElementById( 'adtlogpro-admin-root' );
if ( root ) {
	createRoot( root ).render( <LogsTable /> );
}

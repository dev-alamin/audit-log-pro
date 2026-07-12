import { createRoot, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import LogsTable from './components/LogsTable';
import './style.css';



const root = document.getElementById( 'adtlogpro-admin-root' );
if ( root ) {
    createRoot( root ).render( <LogsTable /> );
}
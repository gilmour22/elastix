<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.0                                                  |
  | http://www.elastix.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | Autores: Alex Villacís Lasso <a_villacis@palosanto.com>              |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/

require_once 'vendor/BaseVendorResource.class.php';
require_once ELASTIX_BASE.'modules/address_book/libs/core.class.php';

class Grandstream extends BaseVendorResource
{
    function handle($id_endpoint, $pathList)
    {
        if (count($pathList) <= 0) {
            header('HTTP/1.1 404 Not Found');
            print 'No '.get_class($this).' resource specified';
            return;
        }
        $service = array_shift($pathList);
        switch ($service) {
        case 'phonebook.xml':
            $this->_handle_phonebook($id_endpoint, $pathList);
            break;
        default:
            header('HTTP/1.1 404 Not Found');
            print 'Unknown '.get_class($this).' resource specified';
            break;
        }
    }

    // Fuente: http://www.grandstream.com/products/gxp_series/general/documents/gxp_wp_xml_phonebook.pdf
    private function _handle_phonebook($id_endpoint)
    {
        $typemap = array('internal', 'external');
        $userdata = $this->obtenerUsuarioElastix($id_endpoint);
        if (is_null($userdata)) {
            header('HTTP/1.1 403 Forbidden');
            print 'Unauthorized for phonebook!';
        	return;
        } 
        else $_SERVER['PHP_AUTH_USER'] = $userdata['name_user'];
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><AddressBook/>');

        $pCore_AddressBook = new core_AddressBook();
        foreach ($typemap as $addressBookType) {
            $result = $pCore_AddressBook->listAddressBook($addressBookType, NULL, NULL, NULL);
            if (!is_array($result)) {
                $error = $pCore_AddressBook->getError();
                if ($error["fc"] == "DBERROR")
                    header("HTTP/1.1 500 Internal Server Error");
                else
                    header("HTTP/1.1 400 Bad Request");
                print $error['fm'].' - '.$error['fd'];
                return;
            }
            
            foreach ($result['extension'] as $contact) {
                $xml_contact = $xml->addChild('Contact');
                // LastName y FirstName deben estar presentes, incluso si vacíos
                if (isset($contact['last_name'])) {
                    $xml_contact->addChild('LastName', str_replace('&', '&amp;', $contact['last_name']));
                    $xml_contact->addChild('FirstName', str_replace('&', '&amp;', $contact['name']));
                } else {
                	$xml_contact->addChild('LastName', str_replace('&', '&amp;', $contact['name']));
                    $xml_contact->addChild('FirstName');
                }
                
                $xml_phone = $xml_contact->addChild('Phone');
                $xml_phone->addChild('phonenumber', str_replace('&', '&amp;', $contact['work_phone']));
                $xml_phone->addChild('accountindex', 0);
            }
            
            // TODO: GXP2200 tiene más campos
            // http://www.grandstream.com/products/gxp_series/gxp2200/documents/gxp2200_xml_phonebook_guide.pdf
        }
    
        header('Content-Type: text/xml');
        print $xml->asXML();
    }
}
?>
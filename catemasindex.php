<?php
date_default_timezone_set("America/Argentina/Buenos_Aires");
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       catemas/catemasindex.php
 *	\ingroup    catemas
 *	\brief      Home page of catemas top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/categories.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

ob_start(); //Con esto, se pueden enviar los headers en cualquier lugar del documento.

// Load translation files required by the page
$langs->loadLangs(array("catemas@catemas"));
$action = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view';
$confirm = GETPOST('confirm', 'alpha');

// Security check
if (!$user->rights->catemas->catemas_porcentaje->read) {
 	accessforbidden();
}


// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;

$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) {
    $page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;


$id = GETPOST('id', 'int');
$max = 10; //registros a mostrar
$now = dol_now();
$multiplicador = 100;//Multiplicador para calculo en tabla
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page


/*
 * Actions
 */
if ($confirm == 'no') {
    if ($backtopage) {
        header("Location: ".$backtopage);
        exit;
    }
}
$categoriasselect = GETPOST('categoriasselect', 'alphanohtml');
$txtporciento = GETPOST('txtporciento', 'double');

/**
 * ACTION CHANGEPRICE 
 */

if ($action == 'changeprice') {
   $error = 0;
    if (empty($categoriasselect)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("msgCategorie")), null, 'warnings');
        $error++;
    }
    if (empty($txtporciento)) {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("msjPercentage")), null, 'warnings');
        $error++;
    }
    if($error==0){
        $arrdata = array();
        $fk_products = array();
         for($i = 0; $i < count($categoriasselect); $i++) {
            $arrdata[] .= $categoriasselect[$i];
            $sql = "REPLACE INTO ".MAIN_DB_PREFIX."calculo_categorias";
            $sql.= "(fk_categorie,porcentaje,fecha)";
            $sql.= "values('".$categoriasselect[$i]."','".((double)$txtporciento)."','".$now."')";
            $result = $db->query($sql);
        }
        if ($result) {
            $sql = "SELECT p.fk_product";
            $sql.= " FROM ".MAIN_DB_PREFIX."categorie_product as p";
            $sql.= " WHERE p.fk_categorie";
            $sql.= " IN (" .rtrim(implode(',',$arrdata), ','). ")";
            $responseqry = $db->query($sql);
            if ($responseqry) {
                $num = $db->num_rows($responseqry);
                if ( $num > 0 ) {
                    while ($row = $db->fetch_array($responseqry)) {
                        $fk_products[] .= $row['fk_product'];
                        
                    }
                    
                    $query="
                    UPDATE llx_product, (
                    SELECT 
                    rowid AS id, 
                    price AS prmax, 
                    price_ttc AS pttc, 
                    price_min AS prmin, 
                    price_min_ttc AS pminttc
                    FROM llx_product
                    WHERE rowid IN (".rtrim(implode(',',$fk_products), ',') .") ) as incre_decre 
                    SET 
                    price = ((incre_decre.prmax) * ".$txtporciento.")+incre_decre.prmax,
                    price_ttc = (incre_decre.pttc * ".$txtporciento.")+incre_decre.pttc,
                    price_min = (incre_decre.prmin * ".$txtporciento.")+incre_decre.prmin,
                    price_min_ttc = (incre_decre.pminttc * ".$txtporciento.")+incre_decre.pminttc
                    WHERE rowid = incre_decre.id
                    ";
                    $result_update = $db->query($query);
          
                    if ($result_update) {
                        setEventMessages($langs->trans('msjUpdatePrices'),null,'mesgs');
                        header("Location: ".DOL_URL_ROOT.'/custom/catemas/catemasindex.php');
                        exit;
                    }else{
                        setEventMessages($langs->trans('msjErrorUpdateData'),null,'errors');
                        exit;
                    
                     }
                }else{
                    
                    setEventMessages($langs->trans('msjNoDataProduct'),null,'warnings');
                    header("Location: ".DOL_URL_ROOT.'/custom/catemas/catemasindex.php');
                    exit;
                }
            }else{
                setEventMessages($langs->trans('msjErrorLoadData'),null,'errors');
                exit;
            }
        }else{
            setEventMessages($langs->trans('msjErrorLoadData'),null,'errors');
            exit;
        }
    }  
}


/*
 * View
 */

$form = new Form($db);

/*
 * BORRAR CATREGORIA DE PRODUCTO
 */

llxHeader("", $langs->trans("CatemasArea"));

print load_fiche_titre($langs->trans("CatemasArea"),'','folder');

print dol_get_fiche_head('');

print '<div class="fichecenter"><div class="fichethirdleft">';

/*END MODULEBUILDER DRAFT MYOBJECT */
if($user->rights->catemas->catemas_porcentaje->write){
    print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" >';		// The target is for brothers that open the file instead of downloading it
    print '<input type="hidden" name="action" value="changeprice">';
    print '<input type="hidden" name="token" value="'.currentToken().'">';
    
    print '	<div class="tagtr">';
    print '	<div class="tagtd">';
    print $form->textwithpicto($langs->trans("txtcategories").' &nbsp; '.$langs->trans(""),$langs->trans("heptxtcategorie"));
    if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire) {
        $categoriesProductArr = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', '', 64, 0, 1);
        print Form::multiselectarray('categoriasselect', $categoriesProductArr, '', 0, 0, 'minwidth300');
    }
    print '</div></div>';
    print '<br>';
    print '	<div class="tagtr">';
    print '	<div class="tagtd">';
    print $form->textwithpicto($langs->trans("txtpercentage").' &nbsp; '.$langs->trans(""), '1 => %100 | 0,1 => %10 | -0,50 => %-50 | -0,05 => %-5');
    print '</div><div class="tagtd maxwidthonsmartphone" style="overflow: hidden; white-space: nowrap;">';
    print '<input size="6" type="number" name="txtporciento" id="txtporciento" step="0.01" value="'.(GETPOST('txtporciento') ? GETPOST('txtporciento', 'int') : '0,00').'">';
    print '</div></div>';
    print '<br>';
    print '<br><input class="button" type="submit" id="submitbtnincrement" '.((GETPOST("") && GETPOST("")) ? '' : 'disabled ').'value="'.$langs->trans('ButtonTextAdd').'">';
    
    print '</form>';  
}
    if ($action == 'delete' && $user->rights->catemas->catemas_porcentaje->delete) {
    	print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$id,$langs->trans('DelPorcentageForCategorie'),$langs->trans('ConfirmDelCategoriePorcentage'), 'confirm_delete','',0,1);
    	//$id = $_GET['id'];
    }
    
    if ($action == 'confirm_delete' && $confirm == 'yes') {
    	$resql = $db->query("DELETE FROM ".MAIN_DB_PREFIX."calculo_categorias WHERE rowid=".$id);
    	if ($resql){
    		
    		header("Location: ".DOL_URL_ROOT.'/custom/catemas/catemasindex.php');
    		
    		exit;
    	}else{
    		header("Location: ".$backtopage);
    		exit;
    	}
    }
    

    print '<script type="text/javascript" language="javascript">
        jQuery(document).ready(function() {
            
            /*sel_categorie=[];
            sel_categorie = jQuery("#categoriasselect").val();
            var txt_percentage = jQuery("#txtporciento").val();
            
            jQuery("#categoriasselect").change(function(e){
                e.preventDefault();
                sel_categorie=[];
        		sel_categorie.push($(this).val());
                console.log(sel_categorie);
        	});

            jQuery("#txtporciento").change(function(){
        		txt_percentage = $(this).val();
                console.log(txt_percentage);
        	});
            
            console.log(sel_categorie, txt_percentage);*/


        function init_gendoc_button()
        	{
        		if (jQuery("#categoriasselect").val() != 0 )
        		{
        			jQuery("#submitbtnincrement").removeAttr("disabled");
        		}
        		else
        		{
        			jQuery("#submitbtnincrement").prop("disabled", true);
        		}
        	}
        	init_gendoc_button();
        jQuery("#categoriasselect").change(function() {
		  init_gendoc_button();
	    });

        });
     </script>';
 
print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';

print '</div></div></div>';

//$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$limit = $max;

/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT */
if (! empty($conf->catemas->enabled)) //&& $user->rights->catemas->read
 {
 $sql = "SELECT s.rowid, s.fk_categorie, s.porcentaje, s.fecha";
 $sql.= " FROM ".MAIN_DB_PREFIX."calculo_categorias as s";
 $sql .= " ORDER BY s.fecha DESC";
 $sql .= $db->plimit($limit, $offset);
 
 $resql = $db->query($sql);
 if ($resql)
 {
 $num = $db->num_rows($resql);
 $i = 0;
 
 $param = '&limit='.$limit;
 print_barre_liste($langs->trans("tableCaption"), $page, $_SERVER["PHP_SELF"], $param , '', '', '', $num + 1 , '', 'list', 0, '', '', $limit ,1,1,0, $langs->trans("morehtml").' ('.$num.')' );
 
 
 print "<table class='noborder' width='100%'>\n";
 print '<tr class="liste_titre">';
 //print '<th>';
 //print $langs->trans("Rowid", $max);
 //print '</th>';
 print '<th>';
 print $langs->trans("tblColHeadCat", $max);
 print '</th>';
 print '<th>%</th>';
 print '<th class="right">'.$langs->trans("tblColHeadDate").'</th>';
 print '<th class="right">'.$langs->trans("tblColHeadOp").'</th>';
 print '</tr>';
 if ($num)
 {
 while ($i < $num)
 {
 $objp = $db->fetch_object($resql);
 
 print '<tr class="oddeven">';
 //print '<td class="nowrap">'.$objp->rowid.'</td>';
 print '<td class="left nowrap">';
 
 $resquery = $db->query("SELECT DISTINCT label FROM llx_categorie AS c LEFT JOIN llx_calculo_categorias cc ON cc.fk_categorie = c.rowid where c.rowid = '".$objp->fk_categorie."' and c.type = 0");
 while ($obj = $db->fetch_object($resquery)) {
     print $obj->label;
 }
 print '</td>';
 print '<td class="left nowrap">'.(($objp->porcentaje)>0?'+'.number_format($objp->porcentaje*$multiplicador,1) : number_format($objp->porcentaje*$multiplicador,1)  ).'</td>'; 
 print '<td class="right nowrap">'.dol_print_date($objp->fecha, "%d/%m/%Y %H:%M")."</td>";

 print '<td class="right nowrap"><a class="deletefilelink" href="'.DOL_URL_ROOT.'/custom/catemas/catemasindex.php?action=delete&token='.newToken().'&id='.$objp->rowid.'">' . img_delete() . '</a></td>';
 
 print '</tr>';
 $i++;
 }

 $db->free($resql);
 } else {
 print '<tr class="oddeven"><td colspan="6" class="opacitymedium">'.$langs->trans("NoHayRegistros").'</td></tr>';
 }
 print "</table><br>";
 }
 }
 
 print dol_get_fiche_end('');
 ob_end_flush(); //Con esto, se pueden enviar los headers en cualquier lugar del documento.
// End of page
llxFooter();
$db->close();

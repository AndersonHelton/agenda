<?php

return  array(
    // Contatos
    '/'                   => 'ContactsController@index',
    '/contatos/criar'     => 'ContactsController@create',
    '/contatos/armazenar' => 'ContactsController@store',
    '/contatos/editar'    => 'ContactsController@edit',
    '/contatos/atualizar' => 'ContactsController@update',
    '/contatos/remover'   => 'ContactsController@delete',

    // Dashboard
    '/admin' => 'DashboardController@index',
);

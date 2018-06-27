<?php

namespace App\Controllers;

use App\Models\Contact;

class ContactsController extends BaseController
{
    /**
     * Listagem de recursos.
     *
     * @return string
     */
    public function index()
    {
        $contact = new Contact;
        $contacts = $contact::fetchAllWhere();

        return $this->view('contacts/index', [
            'contacts' => $contacts,
        ]);
    }

    /**
     * Exibe a página de cadastro de recursos.
     *
     * @return string
     */
    public function create()
    {
        $contact = new Contact;
        $groups = $contact->getGroupList();

        return $this->view('contacts/form', ['groups' => $groups]);

    }

    /**
     * Processa os dados enviados pelo formulário de cadastro.
     *
     * @return void
     */
    public function store($input)
    {
        $input['phones'] = json_encode($input['phones']);

        $contact = new Contact($input);
        $contact->save();

        $flashMsg = urlencode('Cadastrado com sucesso!');
        redirect('/contatos/editar?id=' . $contact->id . '&msg=' . $flashMsg);
    }

    /**
     * Exibe a página de edição de recursos.
     *
     * @return string
     */
    public function edit($input)
    {
        $contact = new Contact;
        $contact = $contact::getById($input['id']);
        $contact->phones = json_decode($contact->phones, true);
         $groups = $contact->getGroupList();

        return $this->view('contacts/form', [
            'contact' => $contact,
            'groups' => $groups
        ]);
    }

    /**
     * Processa os dados enviados pelo formulário de edição.
     *
     * @return void
     */
    public function update( $input)
    {
        $input['phones'] = json_encode($input['phones']);

        $contact = new Contact($input);
        $contact->save();

        $flashMsg = urlencode('Atualizado com sucesso!');
        redirect('/contatos/editar?id=' . $contact->id . '&msg=' . $flashMsg);
    }

    /**
     * Remove um determinado recurso.
     *
     * @return void
     */
    public function delete( $input)
    {
        $contact = new Contact;
        $contact::deleteById($input['id']);

        redirect('/?msg=' . urlencode('Removido com sucesso!'));
    }
}

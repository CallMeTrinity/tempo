import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['email', 'password'];


    connect() {
        console.log('LoginFormController connected');
    }

    submit(event) {
        console.log("log",this.emailTarget.value);
    }
}

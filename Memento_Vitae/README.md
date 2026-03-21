Disclaimer: All of the files are inside the Death_Notes folder, this includes the PHP, CSS, and SQL files.

Database directions:
  1) Download the repository zip, extract to C:\xampp\htdocs or wherever your xampp folder is.
  2) Make sure the file path is C:\xampp\htdocs\Memento_Vitae. This will matter especially on deploying the system using local host.
  3) Open XAMPP shell and create a database named "mementovitae".
       mysql -u root
       create database mementovitae;
       exit;
  4) On exit, change directory to C:\xampp\htdocs\Memento_Vitae and type the following:
       mysql -u root mementovitae < mementovitae.sql
  5) Database is succussfully imported. Open a web browser and go to localhost/Memento_Vitae as this will directly launch the system.


Bacani, Ivan
Bangit, Eisen Josh
Cruzada, Vince Raiezen
Dela Cruz, Anthony Fernan

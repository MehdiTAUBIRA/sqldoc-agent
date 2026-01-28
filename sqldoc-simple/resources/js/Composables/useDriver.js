import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'

export function useDriver() {
  const driverObj = driver({
    showProgress: true,
    showButtons: ['next', 'previous', 'close'],
    steps: []
  })

  function showLoginGuide() {
    driverObj.setSteps([
      {
        element: '#email',
        popover: {
          title: '📧 Adresse e-mail',
          description: "Saisissez ici votre adresse professionnelle liée à votre compte locataire.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#password-field',
        popover: {
          title: '🔒 Mot de passe',
          description: "Entrez votre mot de passe sécurisé pour accéder à votre espace.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#login-button',
        popover: {
          title: '✅ Connexion',
          description: "Une fois les champs remplis, cliquez ici pour vous connecter à votre sous-domaine.",
          side: "top",
          align: 'end'
        },
      },
    ])

    driverObj.drive()
  }

    // FONCTION POUR LES PROJETS
  function showProjectsGuide() {
    driverObj.setSteps([
      {
        element: '#create-project-button',
        popover: {
          title: '➕ Create a new project',
          description: "Click here to create a new project and start documenting your database.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#projects-list',
        popover: {
          title: '📋 Projects list',
          description: "All your projects are displayed here. You can view active or deleted projects.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#project-card-1',
        popover: {
          title: '📁 Project card',
          description: "Each card displays key information about a project. Click 'Open' to access the project dashboard.",
          side: "right",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LA CRÉATION DE PROJET
  function showCreateProjectGuide() {
    driverObj.setSteps([
      {
        element: '#name',
        popover: {
          title: '📝 Project name',
          description: "Enter a clear and descriptive name for your project. This will help you identify it easily.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#description',
        popover: {
          title: '📄 Description',
          description: "Add an optional description to explain the purpose or scope of your project.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#db-type-section',
        popover: {
          title: '🗄️ Database type',
          description: "Select the type of database you want to document. Choose from MySQL, SQL Server, or PostgreSQL.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#cancel-button',
        popover: {
          title: '↩️ Cancel',
          description: "Click here to go back to the projects list without creating a new project.",
          side: "top",
          align: 'end'
        },
      },
      {
        element: '#submit-button',
        popover: {
          title: '✅ Create project',
          description: "Once all fields are filled, click here to create your new project!",
          side: "top",
          align: 'end'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LA CONNEXION AU PROJET
  function showConnectProjectGuide(dbType) {
    const steps = [
      {
        element: '#server',
        popover: {
          title: '🖥️ Server',
          description: "Enter the server address (localhost, IP address, or domain name) where your database is hosted.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#database',
        popover: {
          title: '🗄️ Database name',
          description: "Enter the exact name of the database you want to document.",
          side: "bottom",
          align: 'start'
        },
      },
    ];

    // Ajouter le champ Port uniquement si ce n'est pas SQL Server
    if (dbType !== 'sqlserver') {
      steps.push({
        element: '#port',
        popover: {
          title: '🔌 Port',
          description: "Enter the connection port (default: 3306 for MySQL, 5432 for PostgreSQL).",
          side: "bottom",
          align: 'start'
        },
      });
    }

    // Ajouter les champs d'authentification SQL Server
    if (dbType === 'sqlserver') {
      steps.push({
        element: '#auth-mode-section',
        popover: {
          title: '🔐 Authentication mode',
          description: "Choose between Windows Authentication (uses your Windows session) or SQL Server Authentication (username/password).",
          side: "bottom",
          align: 'start'
        },
      });
    }

    // Champs username/password
    steps.push(
      {
        element: '#username',
        popover: {
          title: '👤 Username',
          description: "Enter the database username with read permissions on the database structure.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#password',
        popover: {
          title: '🔒 Password',
          description: "Enter the password for this user. Your credentials are securely stored and encrypted.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#description',
        popover: {
          title: '📝 Description',
          description: "Add an optional description for this connection (environment, purpose, etc.).",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#test-connection-button',
        popover: {
          title: '🧪 Test connection',
          description: "Click here to verify that the connection parameters are correct before saving.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#submit-connection-button',
        popover: {
          title: '✅ Connect',
          description: "Once the connection is tested and validated, click here to connect and start documenting your database!",
          side: "top",
          align: 'end'
        },
      }
    );

    driverObj.setSteps(steps);
    driverObj.drive();
  }

  // NOUVELLE FONCTION POUR L'ADMINISTRATION
  function showAdminGuide() {
    driverObj.setSteps([
      {
        element: '#roles-permissions-section',
        popover: {
          title: '👑 Roles and permissions',
          description: "Manage system roles and their associated permissions. Configure what each role can do in the application.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#create-user-section',
        popover: {
          title: '➕ Create user',
          description: "Create new users by filling in their information and assigning them a role.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#user-name-field',
        popover: {
          title: '📝 User name',
          description: "Enter the full name of the user.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#user-email-field',
        popover: {
          title: '📧 Email address',
          description: "Enter the user's email address. This will be used for login.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#user-password-field',
        popover: {
          title: '🔒 Password',
          description: "Set a secure password (minimum 8 characters).",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#user-role-field',
        popover: {
          title: '👤 User role',
          description: "Select the role that defines the user's permissions in the system.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#create-user-button',
        popover: {
          title: '✅ Create user',
          description: "Click here to create the user with the specified information.",
          side: "top",
          align: 'end'
        },
      },
      {
        element: '#users-management-section',
        popover: {
          title: '👥 User management',
          description: "View all users, change their roles, and manage their project access rights.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#user-role-dropdown',
        popover: {
          title: '🔄 Change role',
          description: "Change a user's role directly from this dropdown. The change is saved automatically.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#manage-access-button',
        popover: {
          title: '🔑 Manage project access',
          description: "Click here to grant or revoke access to specific projects for this user.",
          side: "left",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES RELEASES
  function showReleaseGuide() {
    driverObj.setSteps([
      {
        element: '#releases-header',
        popover: {
          title: '🏷️ Release management',
          description: "This page allows you to create and manage releases for your project. Each release can be associated with specific database columns.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#search-input',
        popover: {
          title: '🔍 Search',
          description: "Search for releases by version number or description.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#filter-version',
        popover: {
          title: '🎯 Filter by version',
          description: "Quickly filter releases by specific version number.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#add-release-button',
        popover: {
          title: '➕ Add release',
          description: "Click here to create a new release for this project.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#releases-table',
        popover: {
          title: '📋 Releases list',
          description: "View all releases for this project with their details: version number, description, associated columns count, and creation date.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#release-actions',
        popover: {
          title: '⚙️ Actions',
          description: "Edit or delete a release. Note: deleting a release will also remove its associations with columns.",
          side: "left",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES DÉTAILS DE FONCTION
  function showFunctionDetailsGuide() {
    driverObj.setSteps([
      {
        element: '#function-header',
        popover: {
          title: '⚙️ Function details',
          description: "View and manage all information about this database function, including its parameters, definition, and metadata.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#function-description',
        popover: {
          title: '📝 Description',
          description: "Add or edit a description to document the purpose and usage of this function.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#save-description-button',
        popover: {
          title: '💾 Save description',
          description: "Click here to save your changes to the function description.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#function-info',
        popover: {
          title: 'ℹ️ Function information',
          description: "View key metadata: function name, type, return type, creation and modification dates.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#function-tabs',
        popover: {
          title: '📑 Tabs',
          description: "Navigate between Parameters and Definition views to explore different aspects of the function.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#parameters-table',
        popover: {
          title: '📊 Parameters table',
          description: "View all function parameters with their types, output status, descriptions, range values, and release associations.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#parameter-description',
        popover: {
          title: '📄 Parameter description',
          description: "Click the edit icon to add or modify the description of a parameter. Use Ctrl+Enter to save quickly.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-range',
        popover: {
          title: '🎯 Range values',
          description: "Define valid value ranges or constraints for the parameter. Click edit to modify.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-release',
        popover: {
          title: '🏷️ Release association',
          description: "Associate the parameter with a specific release version to track when it was introduced or modified.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-history',
        popover: {
          title: '📜 Modification history',
          description: "View the complete history of changes made to this parameter, including who made changes and when.",
          side: "left",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES DÉTAILS DE PROCÉDURE STOCKÉE
  function showProcedureDetailsGuide() {
    driverObj.setSteps([
      {
        element: '#procedure-header',
        popover: {
          title: '🔧 Stored Procedure details',
          description: "View and manage all information about this stored procedure, including its parameters, definition, and metadata.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#procedure-description',
        popover: {
          title: '📝 Description',
          description: "Add or edit a description to document the purpose and usage of this stored procedure.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#save-description-button',
        popover: {
          title: '💾 Save description',
          description: "Click here to save your changes to the procedure description.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#procedure-info',
        popover: {
          title: 'ℹ️ Procedure information',
          description: "View key metadata: schema, creation date, and last modification date.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#parameters-table',
        popover: {
          title: '📊 Parameters table',
          description: "View all procedure parameters with their types, output status, descriptions, range values, and release associations.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#parameter-description',
        popover: {
          title: '📄 Parameter description',
          description: "Click the edit icon to add or modify the description of a parameter. Use Ctrl+Enter to save quickly.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-range',
        popover: {
          title: '🎯 Range values',
          description: "Define valid value ranges or constraints for the parameter. Click edit to modify.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-release',
        popover: {
          title: '🏷️ Release association',
          description: "Associate the parameter with a specific release version to track when it was introduced or modified.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#parameter-history',
        popover: {
          title: '📜 Modification history',
          description: "View the complete history of changes made to this parameter, including who made changes and when.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#sql-definition',
        popover: {
          title: '💻 SQL Definition',
          description: "View the complete SQL code that defines this stored procedure.",
          side: "top",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES DÉTAILS DE TRIGGER
  function showTriggerDetailsGuide() {
    driverObj.setSteps([
      {
        element: '#trigger-header',
        popover: {
          title: '⚡ Trigger details',
          description: "View and document this database trigger, including its behavior, events, and SQL definition.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#trigger-description',
        popover: {
          title: '📝 Description',
          description: "Add or edit a description to document the purpose, behavior, and impact of this trigger on your database.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#save-description-button',
        popover: {
          title: '💾 Save description',
          description: "Click here to save your changes to the trigger description.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#trigger-info',
        popover: {
          title: 'ℹ️ Trigger information',
          description: "View key metadata: associated table, schema, trigger type (BEFORE/AFTER), event (INSERT/UPDATE/DELETE), status, and creation date.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#trigger-sql',
        popover: {
          title: '💻 SQL Definition',
          description: "View the complete SQL code that defines this trigger's logic and behavior.",
          side: "top",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES DÉTAILS DE TABLE
  function showTableDetailsGuide() {
    driverObj.setSteps([
      {
        element: '#table-header',
        popover: {
          title: '📊 Table details',
          description: "Comprehensive view and management of your database table structure, including columns, indexes, and relationships.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#table-description',
        popover: {
          title: '📝 Table description',
          description: "Document the purpose and usage of this table. Click 'Save modification' to persist your changes.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#save-table-button',
        popover: {
          title: '💾 Save modifications',
          description: "Save all changes made to the table description.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#table-structure',
        popover: {
          title: '🏗️ Table structure',
          description: "View all columns with their properties: name, data type, nullable status, keys, descriptions, range values, and release associations.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#add-column-button',
        popover: {
          title: '➕ Add column',
          description: "Add a new column to the table with full property configuration.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-description',
        popover: {
          title: '📄 Column description',
          description: "Document each column's purpose. Click the edit icon to modify. Use Ctrl+Enter to save quickly. Click the magnifying glass to view full descriptions.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-range',
        popover: {
          title: '🎯 Range values',
          description: "Define valid value ranges, constraints, or examples for the column.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-release',
        popover: {
          title: '🏷️ Column release',
          description: "Associate the column with a specific release to track when it was added or modified.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-history',
        popover: {
          title: '📜 Column history',
          description: "View the complete modification history for each column, including who made changes and when.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#table-indexes',
        popover: {
          title: '🔍 Indexes',
          description: "View all database indexes on this table, including their type, columns, and properties (primary key, unique).",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#table-relations',
        popover: {
          title: '🔗 Relations',
          description: "Manage foreign key relationships with other tables. View constraints and referential actions (CASCADE, SET NULL, etc.).",
          side: "top",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  // NOUVELLE FONCTION POUR LES DÉTAILS DE VUE
  function showViewDetailsGuide() {
    driverObj.setSteps([
      {
        element: '#view-header',
        popover: {
          title: '👁️ View details',
          description: "Comprehensive view of your database view, including its structure, columns, and SQL definition.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#view-description',
        popover: {
          title: '📝 View description',
          description: "Document the purpose and usage of this database view. Click 'Save modification' to persist your changes.",
          side: "bottom",
          align: 'start'
        },
      },
      {
        element: '#save-view-button',
        popover: {
          title: '💾 Save modifications',
          description: "Save all changes made to the view description.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#view-info',
        popover: {
          title: 'ℹ️ View information',
          description: "View key metadata: schema, creation date, and last modification date.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#view-structure',
        popover: {
          title: '🏗️ View structure',
          description: "See all columns exposed by this view with their properties: type, nullable status, length, precision, scale, and documentation.",
          side: "top",
          align: 'start'
        },
      },
      {
        element: '#column-description',
        popover: {
          title: '📄 Column description',
          description: "Document each column's purpose and meaning within the view. Click the edit icon to modify. Use Ctrl+Enter to save quickly.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-range',
        popover: {
          title: '🎯 Range values',
          description: "Define valid value ranges, constraints, or expected values for each column.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-release',
        popover: {
          title: '🏷️ Column release',
          description: "Associate the column with a specific release to track when it was added or modified in the view.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#column-history',
        popover: {
          title: '📜 Column history',
          description: "View the complete modification history for each column, including who made changes and when.",
          side: "left",
          align: 'start'
        },
      },
      {
        element: '#view-sql',
        popover: {
          title: '💻 SQL Definition',
          description: "View the complete SQL query that defines this database view.",
          side: "top",
          align: 'start'
        },
      },
    ])
    
    driverObj.drive()
  }

  return {
    showLoginGuide,
    showProjectsGuide,
    showCreateProjectGuide,
    showConnectProjectGuide,
    showAdminGuide,
    showReleaseGuide,
    showFunctionDetailsGuide,
    showProcedureDetailsGuide,
    showTriggerDetailsGuide,
    showTableDetailsGuide,
    showViewDetailsGuide, 
    driver: driverObj
  }
}
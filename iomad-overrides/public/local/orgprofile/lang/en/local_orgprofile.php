<?php
// This file is part of Moodle - https://moodle.org/

$string['pluginname'] = 'Organization Profiles';
$string['organizationprofiles'] = 'Organization Profiles';
$string['orgtypes'] = 'Organization Types';
$string['usertypes'] = 'User Types';
$string['fields'] = 'Field Library';
$string['forms'] = 'Profile Forms';
$string['categories'] = 'Form Categories';
$string['formfields'] = 'Form Fields';
$string['companymapping'] = 'Company Mapping';
$string['assignments'] = 'User Type Assignment';
$string['settings'] = 'Plugin Settings';
$string['name'] = 'Name';
$string['shortname'] = 'Short name';
$string['description'] = 'Description';
$string['enabled'] = 'Enabled';
$string['sortorder'] = 'Sort order';
$string['orgtype'] = 'Organization type';
$string['companyorgtype'] = 'Organization type';
$string['companyorgtype_help'] = 'Required. This selects the organization-profile catalogue used by the company. ' .
    'It cannot be changed after the company is created.';
$string['orgtypeimmutable'] = 'The organization type is locked after the company is mapped.';
$string['orgtypeimmutable_help'] = 'Changing this value could reinterpret existing user classifications and values, ' .
    'so it is intentionally immutable.';
$string['usertype'] = 'User type';
$string['profileform'] = 'Profile form';
$string['defaultform'] = 'Default profile form';
$string['category'] = 'Category';
$string['field'] = 'Field';
$string['datatype'] = 'Data type';
$string['corefield'] = 'Moodle core field';
$string['notcorefield'] = 'Plugin-defined value';
$string['defaultvalue'] = 'Default value';
$string['required'] = 'Required';
$string['uniquevalue'] = 'Unique value';
$string['uniquescope'] = 'Uniqueness scope';
$string['uniquenone'] = 'Not unique';
$string['uniquecompany'] = 'Unique within company';
$string['uniquesite'] = 'Unique across site';
$string['readonly'] = 'Read only';
$string['visible'] = 'Visible';
$string['sensitive'] = 'Sensitive';
$string['optionsjson'] = 'Menu options (JSON)';
$string['validationjson'] = 'Validation rules (JSON)';
$string['collapsed'] = 'Initially collapsed';
$string['overrideinherit'] = 'Inherit field setting';
$string['overrideyes'] = 'Yes';
$string['overrideno'] = 'No';
$string['company'] = 'Company';
$string['createcompanyprofiled'] = 'Create company with organization profile';
$string['companycreatedwithprofile'] = 'Company created and its organization type was locked successfully.';
$string['companycreateintro'] = 'Create the IOMAD company and select its required organization type. Use IOMAD Edit company ' .
    'afterward for advanced appearance, certificate, email-template, or parent-company settings.';
$string['createprofileduser'] = 'Create profiled user';
$string['createprofileduserfor'] = 'Create profiled user — {$a}';
$string['profiledusercreated'] = 'The Moodle user, IOMAD company membership, user type, and organization profile were ' .
    'created successfully.';
$string['selectusertypeintro'] = 'Select the business user type. The enabled profile form for this company and user type ' .
    'will be loaded on the next step.';
$string['companyusercreateintro'] = 'All visible editable fields are validated on the server using the configured required, ' .
    'format, option, numeric, regex, and uniqueness rules.';
$string['accountdetails'] = 'Moodle account details';
$string['passwordemailrequiresforcechange'] = 'Force password change must be enabled when a password email is requested.';
$string['noenabledorgtypes'] = 'Create and enable at least one organization type before creating a profiled company.';
$string['invalidcompanyshortname'] = 'Use letters, numbers, and underscores only for the company short name.';
$string['companyfieldexists'] = 'That value is already used by another IOMAD company.';
$string['region'] = 'State/Province/Region';
$string['postcode'] = 'Postal code';
$string['user'] = 'User';
$string['status'] = 'Status';
$string['statusactive'] = 'Active';
$string['statusinactive'] = 'Inactive';
$string['actions'] = 'Actions';
$string['manage'] = 'Manage';
$string['addnew'] = 'Add new';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['deleteconfirm'] = 'Delete “{$a}”? This cannot be undone.';
$string['saved'] = 'Changes saved.';
$string['deleted'] = 'Record deleted.';
$string['cannotdeleteinuse'] = 'This record is in use and cannot be deleted.';
$string['invalidshortname'] = 'Use lowercase letters, numbers, and underscores only.';
$string['duplicateshortname'] = 'That short name is already in use in this scope.';
$string['invalidjson'] = 'Enter valid JSON.';
$string['invalidconfiguration'] = 'The field configuration is not valid: {$a}';
$string['invalidrelationship'] = 'The selected records do not have a valid relationship.';
$string['invalidcompanyuser'] = 'The selected user is not an active member of this company.';
$string['companynotmapped'] = 'This company has not been mapped to an organization type.';
$string['usertypenotassigned'] = 'No active user type is assigned for this user and company.';
$string['formnotresolved'] = 'No enabled profile form can be resolved for this user and company.';
$string['noprofilefields'] = 'This profile form has no visible fields.';
$string['valuealreadyused'] = 'This value is already in use within the configured uniqueness scope.';
$string['requiredfield'] = 'This field is required.';
$string['invalidmenuoption'] = 'Select a valid option.';
$string['invalidvalue'] = 'Enter a valid value.';
$string['profilefor'] = '{$a->form} for {$a->user} — {$a->company}';
$string['profileupdated'] = 'Organization profile updated.';
$string['profileupdatedevent'] = 'Organization profile updated';
$string['usertypeassignedevent'] = 'Organization profile user type assigned';
$string['companymappingchangedevent'] = 'Organization profile company mapping changed';
$string['configjson'] = 'Configuration (JSON)';
$string['configjson_help'] = 'Optional non-personal organization configuration. Executable code is not accepted.';
$string['showusernavigation'] = 'Show profile links in user navigation';
$string['showusernavigation_desc'] = 'Add authorized company-scoped organization profile links to Moodle user navigation.';
$string['allowownedit'] = 'Allow own-profile editing';
$string['allowownedit_desc'] = 'The edit-own capability must also be granted. Read-only field rules still apply.';
$string['managementintro'] = 'Configure reusable organization types, user classifications, forms, categories, and fields. ' .
    'Company-scoped operations remain protected by IOMAD company contexts.';
$string['selectcompanyfirst'] = 'Select a company to continue.';
$string['selectformfirst'] = 'Select a form to manage its categories or fields.';
$string['assign'] = 'Assign';
$string['manageformfields'] = 'Manage fields';
$string['aboutthispage'] = 'About this page';
$string['whyrequired'] = 'Why it is required';
$string['search'] = 'Search';
$string['searchplaceholder'] = 'Search by name or short name';
$string['pagesize'] = 'Page size';
$string['filtercontrols'] = 'Filter and page size';
$string['recordsperpage'] = '{$a} records per page';
$string['applyfilters'] = 'Apply filters';
$string['clearfilters'] = 'Clear filters';
$string['sortby'] = 'Sort by {$a}';
$string['noresults'] = 'No records match the current search.';
$string['norecords'] = 'No records have been configured yet.';
$string['statusenabled'] = 'Enabled';
$string['statusdisabled'] = 'Disabled';
$string['configured'] = 'Configured';
$string['notconfigured'] = 'Not configured';
$string['recordcount'] = 'Record count';
$string['requires'] = 'Requires';
$string['edititem'] = 'Edit {$a}';
$string['additem'] = 'Add {$a}';
$string['relatedrecords'] = 'Related records';
$string['relatedsummary'] = '{$a->first} {$a->firstlabel}; {$a->second} {$a->secondlabel}';
$string['companies'] = 'companies';
$string['appliesto'] = 'Applies to';
$string['allusertypes'] = 'All user types';
$string['structure'] = 'Structure';
$string['formstructuresummary'] = '{$a->categories} categories; {$a->fields} fields';
$string['fieldcount'] = 'Fields';
$string['fieldrules'] = 'Rules';
$string['usedinforms'] = 'Used in forms';

$string['orgtypepurpose'] = 'Define the reusable kinds of organizations supported by the site, such as School, University, ' .
    'Hospital, or Corporate Organization.';
$string['orgtypewhy'] = 'Every profiled IOMAD company must select one organization type. It controls which user types and ' .
    'forms are valid for that company.';
$string['orgtypeeditpurpose'] = 'Set the stable name, short name, display order, description, and availability of this ' .
    'organization type.';
$string['usertypepurpose'] = 'Define business profile classifications inside each organization type, such as Student, ' .
    'Teacher, Employee, or Manager.';
$string['usertypewhy'] = 'A user type selects the appropriate profile form; it is classification only and never grants ' .
    'Moodle permissions.';
$string['usertypeeditpurpose'] = 'Create or update a business classification and attach it to exactly one organization type.';
$string['fieldpurpose'] = 'Maintain reusable Moodle-core references and plugin-defined fields together with their validation, ' .
    'visibility, sensitivity, and uniqueness rules.';
$string['fieldwhy'] = 'Fields are defined once and placed on one or more forms, preventing duplicated configuration and ' .
    'inconsistent validation.';
$string['fieldeditpurpose'] = 'Configure one reusable field. JSON configuration accepts only controlled options and validation ' .
    'rules, never executable code.';
$string['formpurpose'] = 'Define the profile form resolved for an organization type and, optionally, a specific user type.';
$string['formwhy'] = 'The resolved form determines which categorized fields a company user sees and which server-side ' .
    'rules are applied.';
$string['formeditpurpose'] = 'Create or update a form and constrain it to a compatible organization type and optional user type.';
$string['categorypurpose'] = 'Create ordered visual sections within profile forms, such as Identity, Address, Employment, ' .
    'or Emergency.';
$string['categorywhy'] = 'Categories make long profiles understandable and provide deterministic display order without ' .
    'owning the field definitions.';
$string['categoryeditpurpose'] = 'Create or update a named form section and its display/collapse behavior.';

$string['formfieldselectpurpose'] = 'Choose a profile form before managing the reusable fields placed inside it.';
$string['formfieldpurpose'] = 'Place field-library entries into this form, choose their category and order, and optionally ' .
    'override required, read-only, or visibility rules.';
$string['formfieldwhy'] = 'A field does not appear in a user profile until it is placed in a category on the resolved form.';
$string['effectiverules'] = 'Effective rules';
$string['requiredshort'] = 'Required';
$string['readonlyshort'] = 'Read only';
$string['visibleshort'] = 'Visible';
$string['managecategories'] = 'Manage categories';
$string['managefieldlibrary'] = 'Manage field library';
$string['addplacement'] = 'Add field placement';
$string['editplacement'] = 'Edit field placement';

$string['companymappingpurpose'] = 'Connect each real IOMAD company to one organization type and optionally choose a compatible ' .
    'default form.';
$string['companymappingwhy'] = 'The mapping is the tenant boundary used to resolve valid user types and forms. The organization ' .
    'type is locked after first mapping to protect existing data.';
$string['companyshortname'] = 'Company short name';
$string['assignedusers'] = 'Assigned users';
$string['automaticformresolution'] = 'Automatic by user type';
$string['addcompanymapping'] = 'Add company mapping';
$string['editcompanymapping'] = 'Edit company mapping';
$string['companymappinglocknote'] = 'On an existing mapping, the company and organization type are locked. ' .
    'The compatible default form and non-personal JSON configuration may still be updated.';

$string['assignmentselectpurpose'] = 'Choose a mapped IOMAD company before managing its company-scoped user classifications.';
$string['assignmentpurpose'] = 'Assign or update one business user type and optional form for a user who is an actual member ' .
    'of this IOMAD company.';
$string['assignmentwhy'] = 'The assignment resolves the dynamic form for this exact user-company relationship and remains ' .
    'separate from Moodle roles and capabilities.';
$string['manageassignments'] = 'Manage user assignments';
$string['viewprofile'] = 'View profile';
$string['assignorupdateusertype'] = 'Assign or update a user type';
$string['assignmentformnote'] = 'Selecting a user who already has an assignment updates that company-scoped assignment. ' .
    'It does not change Moodle roles or membership.';

$string['companycreatepurpose'] = 'Create an IOMAD company and its required organization-profile mapping in one guided operation.';
$string['companycreatewhy'] = 'Creating both records together prevents an incomplete company that cannot resolve user types or ' .
    'dynamic profile forms.';
$string['companyuserselectpurpose'] = 'Start a profiled user by choosing a valid business user type for this company ' .
    'organization type.';
$string['companyusercreatepurpose'] = 'Create the Moodle account, IOMAD membership, company-scoped assignment, and validated ' .
    'profile values as one guided workflow.';
$string['companyusercreatewhy'] = 'The company and user type determine the form before any profile values are accepted; all ' .
    'configured validation is repeated on the server.';
$string['workflowstep'] = 'Step {$a->current} of {$a->total}: {$a->name}';
$string['profilepurpose'] = 'View or edit the dynamic profile resolved for this exact user and IOMAD company relationship.';
$string['profilewhy'] = 'Values are company-scoped, so the same Moodle user may have different classifications and values ' .
    'in different companies without data leakage.';

$string['dashboardpurposeheading'] = 'Company-aware profile configuration';
$string['configurationoverview'] = 'Configuration overview';
$string['ownershipheading'] = 'System ownership';
$string['ownershipnote'] = 'IOMAD owns companies and departments; Moodle owns users, roles, cohorts, courses, and groups; ' .
    'this plugin owns organization types, business user types, form definitions, and company-scoped values.';
$string['orgtypecard'] = 'Top-level organization catalogues used to classify IOMAD companies.';
$string['orgtypedependency'] = 'None; begin here.';
$string['usertypecard'] = 'Business classifications available inside one organization type.';
$string['usertypedependency'] = 'An organization type.';
$string['fieldcard'] = 'Reusable core-field references and custom field validation rules.';
$string['fielddependency'] = 'None; define before form placement.';
$string['formcard'] = 'Dynamic form definitions for organization and user types.';
$string['formdependency'] = 'An organization type and optional user type.';
$string['categorycard'] = 'Ordered sections that organize fields inside forms.';
$string['categorydependency'] = 'A profile form.';
$string['formfieldcard'] = 'Field placements, order, categories, and per-form rule overrides.';
$string['formfielddependency'] = 'A form, category, and field-library entry.';
$string['companymappingcard'] = 'Immutable company-to-organization-type tenant mappings.';
$string['companymappingdependency'] = 'A real IOMAD company and an organization type.';
$string['assignmentcard'] = 'Company-scoped user type and optional form assignments.';
$string['assignmentdependency'] = 'A mapped company and active company membership.';
$string['recommendedworkflow'] = 'Recommended setup workflow';
$string['workfloworgtype'] = 'Create and enable an organization type.';
$string['workflowusertype'] = 'Create its business user types.';
$string['workflowfield'] = 'Create reusable field definitions and validation rules.';
$string['workflowform'] = 'Create forms and categories, then place fields in order.';
$string['workflowcompany'] = 'Create or map an IOMAD company; its organization type becomes immutable.';
$string['workflowassignment'] = 'Create a profiled company user or assign a user type to an existing company member.';
$string['workflowprofile'] = 'Open the resolved profile, validate, and store company-scoped values.';
$string['runtimeoverview'] = 'Runtime data overview';
$string['fieldplacements'] = 'Field placements';
$string['mappedcompanies'] = 'Mapped companies';
$string['storedvalues'] = 'Stored custom values';
$string['runtimenote'] = 'Counts are aggregate configuration/runtime metadata only. Sensitive profile values are never ' .
    'displayed on this dashboard.';
$string['stalemappingwarning'] = '{$a} mapping(s) reference an IOMAD company that no longer exists. They are excluded from ' .
    'company lists and should be reviewed through a controlled data-repair process.';
$string['privacy:metadata:local_orgprofile_user'] = 'Company-scoped profile classification assigned to a user.';
$string['privacy:metadata:local_orgprofile_user:userid'] = 'The user ID.';
$string['privacy:metadata:local_orgprofile_user:companyid'] = 'The IOMAD company ID.';
$string['privacy:metadata:local_orgprofile_user:usertypeid'] = 'The assigned business user type.';
$string['privacy:metadata:local_orgprofile_user:formid'] = 'The explicitly assigned profile form, if any.';
$string['privacy:metadata:local_orgprofile_user:status'] = 'The assignment status.';
$string['privacy:metadata:local_orgprofile_user:timecreated'] = 'When the assignment was created.';
$string['privacy:metadata:local_orgprofile_user:timemodified'] = 'When the assignment was last changed.';
$string['privacy:metadata:local_orgprofile_value'] = 'Company-scoped organization profile values.';
$string['privacy:metadata:local_orgprofile_value:userid'] = 'The user ID.';
$string['privacy:metadata:local_orgprofile_value:companyid'] = 'The IOMAD company ID.';
$string['privacy:metadata:local_orgprofile_value:fieldid'] = 'The profile field ID.';
$string['privacy:metadata:local_orgprofile_value:value'] = 'The stored profile value.';
$string['privacy:metadata:local_orgprofile_value:valuejson'] = 'Structured profile value data, when used.';
$string['privacy:metadata:local_orgprofile_value:uniquekey'] = 'A one-way uniqueness key derived from the value and scope.';
$string['privacy:metadata:local_orgprofile_value:timecreated'] = 'When the value was created.';
$string['privacy:metadata:local_orgprofile_value:timemodified'] = 'When the value was last changed.';
$string['local/orgprofile:manage'] = 'Manage organization profile types';
$string['local/orgprofile:managefields'] = 'Manage organization profile fields';
$string['local/orgprofile:manageforms'] = 'Manage organization profile forms';
$string['local/orgprofile:managecompanymapping'] = 'Manage company organization profile mapping';
$string['local/orgprofile:assignusertype'] = 'Assign company-scoped organization profile user types';
$string['local/orgprofile:viewall'] = 'View all organization profiles';
$string['local/orgprofile:viewcompany'] = 'View organization profiles in own company';
$string['local/orgprofile:viewown'] = 'View own organization profiles';
$string['local/orgprofile:editall'] = 'Edit all organization profiles';
$string['local/orgprofile:editcompany'] = 'Edit organization profiles in own company';
$string['local/orgprofile:editown'] = 'Edit own organization profiles';
$string['local/orgprofile:viewsensitive'] = 'View sensitive organization profile fields';
$string['local/orgprofile:editsensitive'] = 'Edit sensitive organization profile fields';

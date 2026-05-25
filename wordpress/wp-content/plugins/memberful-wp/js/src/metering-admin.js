(() => {
  const container = document.getElementById('memberful-metering-rule-groups');
  const addGroupButton = document.getElementById('memberful-metering-add-group');
  const ns = window.memberfulMeteringAdmin;

  if (!container || !addGroupButton || !ns) {
    return;
  }

  const { fields, operators, postTypes, labels } = ns;

  const namePrefix = (groupIndex, conditionIndex) =>
    `memberful_metering[rules][${groupIndex}][conditions][${conditionIndex}]`;

  const appendOptions = (select, options, selectedValues = []) => {
    Object.entries(options).forEach(([value, label]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;

      if (selectedValues.includes(value)) {
        option.selected = true;
      }

      select.appendChild(option);
    });
  };

  const createFieldSelect = (groupIndex, conditionIndex, selectedField) => {
    const select = document.createElement('select');
    select.className = 'memberful-metering-condition-field';
    select.name = `${namePrefix(groupIndex, conditionIndex)}[field]`;
    appendOptions(select, fields, [selectedField]);

    return select;
  };

  const createOperatorSelect = (groupIndex, conditionIndex, field) => {
    const select = document.createElement('select');
    select.name = `${namePrefix(groupIndex, conditionIndex)}[operator]`;
    appendOptions(select, operators[field] || {});

    return select;
  };

  const createValuesControl = (groupIndex, conditionIndex, field) => {
    if (field === 'post_type') {
      const select = document.createElement('select');
      select.multiple = true;
      select.name = `${namePrefix(groupIndex, conditionIndex)}[values][]`;
      select.className = 'memberful-metering-values--post-type';
      appendOptions(select, postTypes);
      return select;
    }

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'regular-text';
    input.name = `${namePrefix(groupIndex, conditionIndex)}[values]`;
    return input;
  };

  const renderOperatorAndValues = (row, groupIndex, conditionIndex, field) => {
    const operatorCell = row.querySelector('.memberful-metering-condition-operator');
    const valuesCell = row.querySelector('.memberful-metering-condition-values');

    operatorCell.replaceChildren(createOperatorSelect(groupIndex, conditionIndex, field));
    valuesCell.replaceChildren(createValuesControl(groupIndex, conditionIndex, field));
  };

  const createConditionRow = (groupIndex, conditionIndex) => {
    const field = 'post_type';
    const row = document.createElement('tr');
    const fieldCell = document.createElement('td');
    const operatorCell = document.createElement('td');
    const valuesCell = document.createElement('td');
    const actionCell = document.createElement('td');
    const fieldSelect = createFieldSelect(groupIndex, conditionIndex, field);
    const removeButton = document.createElement('button');

    row.dataset.conditionIndex = conditionIndex;
    operatorCell.className = 'memberful-metering-condition-operator';
    valuesCell.className = 'memberful-metering-condition-values';

    fieldSelect.addEventListener('change', () => {
      renderOperatorAndValues(row, groupIndex, conditionIndex, fieldSelect.value);
    });

    removeButton.type = 'button';
    removeButton.className = 'button memberful-metering-remove-condition';
    removeButton.textContent = labels.remove;
    removeButton.addEventListener('click', () => {
      row.remove();
    });

    fieldCell.appendChild(fieldSelect);
    operatorCell.appendChild(createOperatorSelect(groupIndex, conditionIndex, field));
    valuesCell.appendChild(createValuesControl(groupIndex, conditionIndex, field));
    actionCell.appendChild(removeButton);

    row.append(fieldCell, operatorCell, valuesCell, actionCell);

    return row;
  };

  const updateEmptyMessage = () => {
    const emptyMessage = document.getElementById('memberful-metering-empty-rules');
    const hasGroups = container.querySelectorAll('.memberful-metering-rule-group').length > 0;

    if (emptyMessage) {
      emptyMessage.style.display = hasGroups ? 'none' : '';
    }
  };

  const bindGroup = (group) => {
    const addConditionButton = group.querySelector('.memberful-metering-add-condition');
    const removeGroupButton = group.querySelector('.memberful-metering-remove-group');
    const { groupIndex } = group.dataset;

    addConditionButton?.addEventListener('click', () => {
      const nextConditionIndex = parseInt(group.dataset.nextConditionIndex || '0', 10);
      const tbody = group.querySelector('tbody');

      tbody.appendChild(createConditionRow(groupIndex, nextConditionIndex));
      group.dataset.nextConditionIndex = nextConditionIndex + 1;
    });

    removeGroupButton?.addEventListener('click', () => {
      group.remove();
      updateEmptyMessage();
    });
  };

  const createGroup = (groupIndex) => {
    const group = document.createElement('div');
    const heading = document.createElement('h4');
    const removeGroupButton = document.createElement('button');
    const summary = document.createElement('p');
    const matchSelect = document.createElement('select');
    const table = document.createElement('table');
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    const tbody = document.createElement('tbody');
    const addConditionParagraph = document.createElement('p');
    const addConditionButton = document.createElement('button');

    group.className = 'memberful-metering-rule-group';
    group.dataset.groupIndex = groupIndex;
    group.dataset.nextConditionIndex = 1;

    heading.appendChild(document.createTextNode(`${labels.group} `));
    removeGroupButton.type = 'button';
    removeGroupButton.className = 'button-link memberful-metering-remove-group';
    removeGroupButton.textContent = labels.remove;
    heading.appendChild(removeGroupButton);

    matchSelect.name = `memberful_metering[rules][${groupIndex}][match]`;
    appendOptions(matchSelect, { all: labels.all, any: labels.any }, ['all']);

    summary.append(
      document.createTextNode(`${labels.meterContentWhen} `),
      matchSelect,
      document.createTextNode(` ${labels.ofTheseConditions}`),
    );

    [labels.field, labels.operator, labels.values, labels.actions].forEach((label) => {
      const th = document.createElement('th');
      th.textContent = label;
      headerRow.appendChild(th);
    });

    table.className = 'widefat striped memberful-metering-conditions';
    thead.appendChild(headerRow);
    table.append(thead, tbody);
    tbody.appendChild(createConditionRow(groupIndex, 0));

    addConditionButton.type = 'button';
    addConditionButton.className = 'button memberful-metering-add-condition';
    addConditionButton.textContent = labels.addCondition;
    addConditionParagraph.appendChild(addConditionButton);

    group.append(heading, summary, table, addConditionParagraph);

    bindGroup(group);

    return group;
  };

  container.querySelectorAll('.memberful-metering-rule-group').forEach(bindGroup);

  container.querySelectorAll('.memberful-metering-condition-field').forEach((fieldSelect) => {
    fieldSelect.addEventListener('change', () => {
      const row = fieldSelect.closest('tr');
      const group = fieldSelect.closest('.memberful-metering-rule-group');

      renderOperatorAndValues(row, group.dataset.groupIndex, row.dataset.conditionIndex, fieldSelect.value);
    });
  });

  container.querySelectorAll('.memberful-metering-remove-condition').forEach((removeButton) => {
    removeButton.addEventListener('click', () => {
      removeButton.closest('tr').remove();
    });
  });

  addGroupButton.addEventListener('click', () => {
    const nextGroupIndex = parseInt(container.dataset.nextGroupIndex || '0', 10);

    container.appendChild(createGroup(nextGroupIndex));
    container.dataset.nextGroupIndex = nextGroupIndex + 1;
    updateEmptyMessage();
  });

  updateEmptyMessage();
})();
